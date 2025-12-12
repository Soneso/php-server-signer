<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Config;

use InvalidArgumentException;
use RuntimeException;

/**
 * Configuration class for the server signer.
 * Holds all configuration parameters for server operation, Stellar keypair, and network settings.
 */
final readonly class Config
{
    /**
     * @param string $host Server host address
     * @param int $port Server port number
     * @param string $accountId Stellar account ID (public key)
     * @param string $secret Stellar secret key
     * @param string $networkPassphrase Network passphrase for transaction signing
     * @param string $sorobanRpcUrl Soroban RPC endpoint URL
     * @param string $bearerToken Authentication bearer token
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $accountId,
        public string $secret,
        public string $networkPassphrase,
        public string $sorobanRpcUrl,
        public string $bearerToken,
    ) {
    }

    /**
     * Load configuration from a JSON file.
     *
     * @param string $path Path to the JSON configuration file
     * @return self Configured instance
     * @throws RuntimeException If file cannot be read or parsed
     * @throws InvalidArgumentException If required fields are missing
     */
    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Config file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file: {$path}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException("Failed to parse config file: {$path}");
        }

        return self::fromArray($data);
    }

    /**
     * Load configuration from environment variables.
     *
     * @return self Configured instance
     * @throws InvalidArgumentException If required environment variables are missing
     */
    public static function fromEnv(): self
    {
        $data = [
            'host' => self::getEnv('HOST', '0.0.0.0'),
            'port' => self::getEnvInt('PORT', 5003),
            'account_id' => self::getEnv('ACCOUNT_ID', ''),
            'secret' => self::getEnv('SECRET', ''),
            'network_passphrase' => self::getEnv('NETWORK_PASSPHRASE', 'Test SDF Network ; September 2015'),
            'soroban_rpc_url' => self::getEnv('SOROBAN_RPC_URL', 'https://soroban-testnet.stellar.org'),
            'bearer_token' => self::getEnv('BEARER_TOKEN', '987654321'),
        ];

        return self::fromArray($data);
    }

    /**
     * Create configuration from an array of data.
     *
     * @param array<string, mixed> $data Configuration data
     * @return self Configured instance
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    private static function fromArray(array $data): self
    {
        // Extract values with defaults
        $host = (string)($data['host'] ?? '0.0.0.0');
        $port = (int)($data['port'] ?? 5003);
        $accountId = (string)($data['account_id'] ?? '');
        $secret = (string)($data['secret'] ?? '');
        $networkPassphrase = (string)($data['network_passphrase'] ?? 'Test SDF Network ; September 2015');
        $sorobanRpcUrl = (string)($data['soroban_rpc_url'] ?? 'https://soroban-testnet.stellar.org');
        $bearerToken = (string)($data['bearer_token'] ?? '');

        // Apply defaults for empty values
        if ($host === '') {
            $host = '0.0.0.0';
        }
        if ($port === 0) {
            $port = 5003;
        }
        if ($networkPassphrase === '') {
            $networkPassphrase = 'Test SDF Network ; September 2015';
        }
        if ($sorobanRpcUrl === '') {
            $sorobanRpcUrl = 'https://soroban-testnet.stellar.org';
        }

        // Validate required fields
        if ($accountId === '') {
            throw new InvalidArgumentException('account_id is required');
        }
        if ($secret === '') {
            throw new InvalidArgumentException('secret is required');
        }
        if ($bearerToken === '') {
            throw new InvalidArgumentException('bearer_token is required');
        }

        // Validate port range
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port number: {$port}");
        }

        return new self(
            host: $host,
            port: $port,
            accountId: $accountId,
            secret: $secret,
            networkPassphrase: $networkPassphrase,
            sorobanRpcUrl: $sorobanRpcUrl,
            bearerToken: $bearerToken,
        );
    }

    /**
     * Get environment variable with default value.
     *
     * @param string $key Environment variable name
     * @param string $default Default value if not set
     * @return string Environment variable value or default
     */
    private static function getEnv(string $key, string $default): string
    {
        $value = getenv($key);
        return $value !== false && $value !== '' ? $value : $default;
    }

    /**
     * Get integer environment variable with default value.
     *
     * @param string $key Environment variable name
     * @param int $default Default value if not set
     * @return int Environment variable value or default
     */
    private static function getEnvInt(string $key, int $default): int
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue !== false ? $intValue : $default;
    }
}
