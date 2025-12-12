<?php

declare(strict_types=1);

namespace Soneso\ServerSigner;

use Soneso\ServerSigner\Config\Config;
use Soneso\ServerSigner\Handler\Router;
use Soneso\ServerSigner\Signer\Sep10Signer;
use Soneso\ServerSigner\Signer\Sep45Signer;

// Set error reporting for production
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Load Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    if (PHP_SAPI === 'cli') {
        fwrite(\STDERR, "Error: Composer autoloader not found. Run 'composer install'.\n");
        exit(1);
    } else {
        error_log("Error: Composer autoloader not found. Run 'composer install'.");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Server configuration error']);
        exit(1);
    }
}

// Parse command line arguments when running as CLI
$configPath = null;
if (PHP_SAPI === 'cli') {
    $options = getopt('c:', ['config:']);
    $configPath = $options['config'] ?? $options['c'] ?? null;
}

// Auto-detect config.json in parent directory if not specified
if ($configPath === null) {
    $defaultConfigPath = __DIR__ . '/../config.json';
    if (file_exists($defaultConfigPath)) {
        $configPath = $defaultConfigPath;
    }
}

// Load configuration
try {
    if ($configPath !== null) {
        $config = Config::fromFile($configPath);
        if (PHP_SAPI === 'cli') {
            Router::log("Loaded configuration from file: {$configPath}");
        }
    } else {
        $config = Config::fromEnv();
        if (PHP_SAPI === 'cli') {
            Router::log('Loaded configuration from environment variables');
        }
    }
} catch (\Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(\STDERR, "Configuration error: {$e->getMessage()}\n");
        exit(1);
    } else {
        error_log("Configuration error: {$e->getMessage()}");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Configuration error']);
        exit(1);
    }
}

// Create router and register routes
$router = new Router();
$router->setBearerToken($config->bearerToken);

// Health check endpoint (no authentication required)
$router->get('/health', function () use ($router): void {
    $router->sendResponse(200, ['status' => 'ok']);
});

// Stellar TOML endpoint (no authentication required)
$router->get('/.well-known/stellar.toml', function () use ($config): void {
    $toml = sprintf(
        "ACCOUNTS = [\"%s\"]\nSIGNING_KEY = \"%s\"\nNETWORK_PASSPHRASE = \"%s\"\n",
        $config->accountId,
        $config->accountId,
        $config->networkPassphrase
    );

    http_response_code(200);
    header('Content-Type: text/plain');
    echo $toml;
    exit;
});

// SEP-10 signing endpoint (requires authentication)
$router->post('/sign-sep-10', function () use ($router, $config): void {
    try {
        $body = $router->getJsonBody();

        // Validate required fields
        if (!isset($body['transaction']) || !is_string($body['transaction']) || $body['transaction'] === '') {
            $router->sendError(400, 'missing transaction parameter');
            return;
        }
        if (!isset($body['network_passphrase']) || !is_string($body['network_passphrase']) || $body['network_passphrase'] === '') {
            $router->sendError(400, 'missing network_passphrase parameter');
            return;
        }

        // Sign the transaction
        $signedTransaction = Sep10Signer::sign(
            $body['transaction'],
            $body['network_passphrase'],
            $config->secret
        );

        // Return the signed transaction
        $router->sendResponse(200, [
            'transaction' => $signedTransaction,
            'network_passphrase' => $body['network_passphrase'],
        ]);
    } catch (\InvalidArgumentException $e) {
        $router->sendError(400, $e->getMessage());
    } catch (\Throwable $e) {
        error_log("SEP-10 signing error: {$e->getMessage()}");
        $router->sendError(500, 'Internal server error');
    }
});
$router->requireAuth('/sign-sep-10');

// SEP-45 signing endpoint (requires authentication)
$router->post('/sign-sep-45', function () use ($router, $config): void {
    try {
        $body = $router->getJsonBody();

        // Validate required fields
        if (!isset($body['authorization_entry']) || !is_string($body['authorization_entry']) || $body['authorization_entry'] === '') {
            $router->sendError(400, 'missing authorization_entry parameter');
            return;
        }
        if (!isset($body['network_passphrase']) || !is_string($body['network_passphrase']) || $body['network_passphrase'] === '') {
            $router->sendError(400, 'missing network_passphrase parameter');
            return;
        }

        // Sign the authorization entry
        $signedEntry = Sep45Signer::sign(
            $body['authorization_entry'],
            $body['network_passphrase'],
            $config->secret,
            $config->sorobanRpcUrl
        );

        // Return the signed authorization entry
        $router->sendResponse(200, [
            'authorization_entry' => $signedEntry,
            'network_passphrase' => $body['network_passphrase'],
        ]);
    } catch (\InvalidArgumentException $e) {
        $router->sendError(400, $e->getMessage());
    } catch (\Throwable $e) {
        error_log("SEP-45 signing error: {$e->getMessage()}");
        $router->sendError(500, 'Internal server error');
    }
});
$router->requireAuth('/sign-sep-45');

// If running as CLI, start the built-in server
if (PHP_SAPI === 'cli') {
    $address = "{$config->host}:{$config->port}";

    Router::log("Starting server on {$address}");
    Router::log("Account ID: {$config->accountId}");
    Router::log("Network Passphrase: {$config->networkPassphrase}");
    Router::log("Endpoints:");
    Router::log("  POST   /sign-sep-10");
    Router::log("  POST   /sign-sep-45");
    Router::log("  GET    /.well-known/stellar.toml");
    Router::log("  GET    /health");
    Router::log("");

    // Export configuration as environment variables for child server process
    putenv("HOST={$config->host}");
    putenv("PORT={$config->port}");
    putenv("ACCOUNT_ID={$config->accountId}");
    putenv("SECRET={$config->secret}");
    putenv("NETWORK_PASSPHRASE={$config->networkPassphrase}");
    putenv("SOROBAN_RPC_URL={$config->sorobanRpcUrl}");
    putenv("BEARER_TOKEN={$config->bearerToken}");

    // Build PHP command
    $docRoot = __DIR__;
    $routerScript = __FILE__;

    // Escape arguments for shell execution
    $addressEscaped = escapeshellarg($address);
    $docRootEscaped = escapeshellarg($docRoot);
    $routerScriptEscaped = escapeshellarg($routerScript);

    // Build command with proper escaping
    $command = sprintf(
        'php -d opcache.enable=0 -d opcache.enable_cli=0 -S %s -t %s %s 2>&1',
        $addressEscaped,
        $docRootEscaped,
        $routerScriptEscaped
    );

    // Execute the built-in server
    passthru($command);
} else {
    // Handle HTTP request
    $router->handle();
}
