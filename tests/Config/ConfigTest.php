<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Tests\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Soneso\ServerSigner\Config\Config;

/**
 * Test suite for Config class
 */
final class ConfigTest extends TestCase
{
    private const TEST_CONFIG_PATH = '/tmp/test_config.json';
    private const TEST_ACCOUNT_ID = 'GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV';
    private const TEST_SECRET = 'SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG';
    private const TEST_BEARER_TOKEN = 'test-token-12345';

    protected function tearDown(): void
    {
        // Clean up test config file
        if (file_exists(self::TEST_CONFIG_PATH)) {
            unlink(self::TEST_CONFIG_PATH);
        }

        // Clean up environment variables
        putenv('HOST');
        putenv('PORT');
        putenv('ACCOUNT_ID');
        putenv('SECRET');
        putenv('NETWORK_PASSPHRASE');
        putenv('SOROBAN_RPC_URL');
        putenv('BEARER_TOKEN');
    }

    #[Test]
    public function it_loads_config_from_valid_json_file(): void
    {
        // Create test config file
        $configData = [
            'host' => '127.0.0.1',
            'port' => 8080,
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
            'network_passphrase' => 'Test SDF Network ; September 2015',
            'soroban_rpc_url' => 'https://soroban-testnet.stellar.org',
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $config = Config::fromFile(self::TEST_CONFIG_PATH);

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(8080, $config->port);
        $this->assertSame(self::TEST_ACCOUNT_ID, $config->accountId);
        $this->assertSame(self::TEST_SECRET, $config->secret);
        $this->assertSame('Test SDF Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-testnet.stellar.org', $config->sorobanRpcUrl);
        $this->assertSame(self::TEST_BEARER_TOKEN, $config->bearerToken);
    }

    #[Test]
    public function it_applies_default_values_when_loading_from_file(): void
    {
        // Create minimal config file with only required fields
        $configData = [
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $config = Config::fromFile(self::TEST_CONFIG_PATH);

        // Verify defaults are applied
        $this->assertSame('0.0.0.0', $config->host);
        $this->assertSame(5003, $config->port);
        $this->assertSame('Test SDF Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-testnet.stellar.org', $config->sorobanRpcUrl);
    }

    #[Test]
    public function it_throws_exception_when_file_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');

        Config::fromFile('/nonexistent/path/config.json');
    }

    #[Test]
    public function it_throws_exception_when_json_is_invalid(): void
    {
        // Create invalid JSON file
        file_put_contents(self::TEST_CONFIG_PATH, '{invalid json}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse config file');

        Config::fromFile(self::TEST_CONFIG_PATH);
    }

    #[Test]
    public function it_throws_exception_when_account_id_is_missing(): void
    {
        $configData = [
            'secret' => self::TEST_SECRET,
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('account_id is required');

        Config::fromFile(self::TEST_CONFIG_PATH);
    }

    #[Test]
    public function it_throws_exception_when_secret_is_missing(): void
    {
        $configData = [
            'account_id' => self::TEST_ACCOUNT_ID,
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secret is required');

        Config::fromFile(self::TEST_CONFIG_PATH);
    }

    #[Test]
    public function it_throws_exception_when_bearer_token_is_missing(): void
    {
        $configData = [
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bearer_token is required');

        Config::fromFile(self::TEST_CONFIG_PATH);
    }

    #[Test]
    #[DataProvider('invalidPortProvider')]
    public function it_throws_exception_for_invalid_port(int $port): void
    {
        $configData = [
            'port' => $port,
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port number');

        Config::fromFile(self::TEST_CONFIG_PATH);
    }

    public static function invalidPortProvider(): array
    {
        return [
            // Note: port 0 is replaced with default (5003) before validation
            'port negative' => [-1],
            'port too high' => [65536],
            'port way too high' => [100000],
        ];
    }

    #[Test]
    public function it_loads_config_from_environment_variables(): void
    {
        putenv('HOST=192.168.1.1');
        putenv('PORT=9000');
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('NETWORK_PASSPHRASE=Public Global Stellar Network ; September 2015');
        putenv('SOROBAN_RPC_URL=https://soroban-mainnet.stellar.org');
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $config = Config::fromEnv();

        $this->assertSame('192.168.1.1', $config->host);
        $this->assertSame(9000, $config->port);
        $this->assertSame(self::TEST_ACCOUNT_ID, $config->accountId);
        $this->assertSame(self::TEST_SECRET, $config->secret);
        $this->assertSame('Public Global Stellar Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-mainnet.stellar.org', $config->sorobanRpcUrl);
        $this->assertSame(self::TEST_BEARER_TOKEN, $config->bearerToken);
    }

    #[Test]
    public function it_applies_default_values_from_environment(): void
    {
        // Set only required environment variables
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $config = Config::fromEnv();

        // Verify defaults are applied
        $this->assertSame('0.0.0.0', $config->host);
        $this->assertSame(5003, $config->port);
        $this->assertSame('Test SDF Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-testnet.stellar.org', $config->sorobanRpcUrl);
    }

    #[Test]
    public function it_handles_empty_environment_variables_with_defaults(): void
    {
        putenv('HOST=');
        putenv('PORT=');
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('NETWORK_PASSPHRASE=');
        putenv('SOROBAN_RPC_URL=');
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $config = Config::fromEnv();

        // Empty values should be replaced with defaults
        $this->assertSame('0.0.0.0', $config->host);
        $this->assertSame(5003, $config->port);
        $this->assertSame('Test SDF Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-testnet.stellar.org', $config->sorobanRpcUrl);
    }

    #[Test]
    public function it_throws_exception_when_env_account_id_is_missing(): void
    {
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('account_id is required');

        Config::fromEnv();
    }

    #[Test]
    public function it_throws_exception_when_env_secret_is_missing(): void
    {
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secret is required');

        Config::fromEnv();
    }

    #[Test]
    public function it_uses_default_bearer_token_from_env_when_not_set(): void
    {
        // fromEnv() has a default bearer token of '987654321'
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        // Don't set BEARER_TOKEN

        $config = Config::fromEnv();

        // Should use the default bearer token
        $this->assertSame('987654321', $config->bearerToken);
    }

    #[Test]
    public function it_parses_port_as_integer_from_environment(): void
    {
        putenv('PORT=8888');
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $config = Config::fromEnv();

        $this->assertSame(8888, $config->port);
        $this->assertIsInt($config->port);
    }

    #[Test]
    public function it_handles_invalid_port_string_in_environment(): void
    {
        putenv('PORT=not-a-number');
        putenv('ACCOUNT_ID=' . self::TEST_ACCOUNT_ID);
        putenv('SECRET=' . self::TEST_SECRET);
        putenv('BEARER_TOKEN=' . self::TEST_BEARER_TOKEN);

        $config = Config::fromEnv();

        // Should fall back to default when port is invalid
        $this->assertSame(5003, $config->port);
    }

    #[Test]
    public function it_replaces_empty_string_values_with_defaults(): void
    {
        $configData = [
            'host' => '',
            'port' => 0,
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
            'network_passphrase' => '',
            'soroban_rpc_url' => '',
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $config = Config::fromFile(self::TEST_CONFIG_PATH);

        // Empty strings and zero should be replaced with defaults
        $this->assertSame('0.0.0.0', $config->host);
        $this->assertSame(5003, $config->port);
        $this->assertSame('Test SDF Network ; September 2015', $config->networkPassphrase);
        $this->assertSame('https://soroban-testnet.stellar.org', $config->sorobanRpcUrl);
    }

    #[Test]
    public function it_is_readonly_and_cannot_be_modified(): void
    {
        $configData = [
            'account_id' => self::TEST_ACCOUNT_ID,
            'secret' => self::TEST_SECRET,
            'bearer_token' => self::TEST_BEARER_TOKEN,
        ];

        file_put_contents(self::TEST_CONFIG_PATH, json_encode($configData));

        $config = Config::fromFile(self::TEST_CONFIG_PATH);

        // Verify that the config class is readonly
        $reflection = new \ReflectionClass($config);
        $this->assertTrue($reflection->isReadOnly(), 'Config class should be readonly');
    }
}
