<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Tests\Signer;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soneso\ServerSigner\Signer\Sep45Signer;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Soroban\Address;
use Soneso\StellarSDK\Soroban\SorobanAddressCredentials;
use Soneso\StellarSDK\Soroban\SorobanAuthorizationEntry;
use Soneso\StellarSDK\Soroban\SorobanAuthorizedFunction;
use Soneso\StellarSDK\Soroban\SorobanAuthorizedInvocation;
use Soneso\StellarSDK\Soroban\SorobanCredentials;
use Soneso\StellarSDK\Xdr\XdrSCVal;

/**
 * Test suite for SEP-45 authorization entry signer
 *
 * Tests use the PHP SDK's high-level SorobanAuthorizationEntry classes
 * to create test fixtures, demonstrating proper SDK usage patterns.
 */
final class Sep45SignerTest extends TestCase
{
    private const TEST_SECRET = 'SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG';
    private const TEST_ACCOUNT_ID = 'GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV';
    private const TEST_NETWORK = 'Test SDF Network ; September 2015';
    private const TEST_RPC_URL = 'https://soroban-testnet.stellar.org';

    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::fromSeed(self::TEST_SECRET);
    }

    #[Test]
    public function it_throws_exception_for_empty_entry_xdr(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('authorization_entry XDR cannot be empty');

        Sep45Signer::sign('', self::TEST_NETWORK, self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_empty_network_passphrase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('network passphrase cannot be empty');

        Sep45Signer::sign('some_xdr', '', self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_empty_secret_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secret key cannot be empty');

        Sep45Signer::sign('some_xdr', self::TEST_NETWORK, '', self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_empty_soroban_rpc_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('soroban RPC URL cannot be empty');

        Sep45Signer::sign('some_xdr', self::TEST_NETWORK, self::TEST_SECRET, '');
    }

    #[Test]
    public function it_throws_exception_for_invalid_base64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to decode authorization entry');

        Sep45Signer::sign('invalid!!!base64', self::TEST_NETWORK, self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_invalid_secret_key(): void
    {
        // Create a valid entry
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);
        $entryXDR = $entry->toBase64Xdr();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse secret key');

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, 'INVALID_SECRET', self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_public_key_instead_of_secret(): void
    {
        // Create a valid entry
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);
        $entryXDR = $entry->toBase64Xdr();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse secret key');

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_ACCOUNT_ID, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_malformed_entry_xdr(): void
    {
        // Create invalid XDR data
        $entryXDR = base64_encode('invalid_entry_data');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to decode authorization entry');

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_when_entry_address_does_not_match_signing_key(): void
    {
        // Create an entry with a different account ID
        $differentAccount = 'GBCR5OVQ54S2EKHLBZMK6VYMTXZHXN3T45Y6PRX4PX4FXDMJJGY4FD42';
        $entry = $this->createTestAuthorizationEntry($differentAccount);
        $entryXDR = $entry->toBase64Xdr();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entry address does not match signing key');

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_throws_exception_for_entry_without_address_credentials(): void
    {
        // Create entry with source account credentials (not address credentials)
        $rootInvocation = $this->createTestRootInvocation();
        $credentials = SorobanCredentials::forSourceAccount();
        $entry = new SorobanAuthorizationEntry($credentials, $rootInvocation);
        $entryXDR = $entry->toBase64Xdr();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entry must use address credentials');

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_SECRET, self::TEST_RPC_URL);
    }

    #[Test]
    public function it_handles_rpc_http_error(): void
    {
        // Create a valid entry using high-level SDK classes
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);
        $entryXDR = $entry->toBase64Xdr();

        $this->expectException(\Throwable::class);

        // Test RPC error via invalid URL
        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_SECRET, 'http://invalid-host-that-does-not-exist-12345.test');
    }

    #[Test]
    public function it_handles_rpc_connection_error(): void
    {
        // Create a valid entry
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);
        $entryXDR = $entry->toBase64Xdr();

        // This will fail when trying to connect to an unreachable RPC endpoint
        $this->expectException(\Throwable::class);

        Sep45Signer::sign($entryXDR, self::TEST_NETWORK, self::TEST_SECRET, 'http://localhost:0');
    }

    #[Test]
    public function it_validates_and_parses_valid_authorization_entry(): void
    {
        // Create a valid authorization entry using high-level SDK classes
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);

        // Encode to base64 XDR
        $entryXDR = $entry->toBase64Xdr();

        // Verify we can decode it back
        $decoded = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);
        $this->assertInstanceOf(SorobanAuthorizationEntry::class, $decoded);
    }

    #[Test]
    public function it_encodes_and_decodes_entry_with_address_credentials(): void
    {
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);

        $entryXDR = $entry->toBase64Xdr();
        $decoded = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);

        $this->assertNotNull($decoded->credentials->addressCredentials);
        $this->assertSame(0, $decoded->credentials->addressCredentials->signatureExpirationLedger);
    }

    #[Test]
    public function it_validates_nonce_in_authorization_entry(): void
    {
        $testNonce = 12345;
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID, $testNonce);

        $entryXDR = $entry->toBase64Xdr();
        $decoded = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);

        $addrCreds = $decoded->credentials->addressCredentials;
        $this->assertNotNull($addrCreds);
        $this->assertSame($testNonce, $addrCreds->nonce);
    }

    #[Test]
    public function it_preserves_root_invocation_in_entry(): void
    {
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);

        $entryXDR = $entry->toBase64Xdr();
        $decoded = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);

        $rootInvocation = $decoded->rootInvocation;

        $this->assertInstanceOf(SorobanAuthorizedInvocation::class, $rootInvocation);
    }

    #[Test]
    public function it_validates_address_in_credentials(): void
    {
        $entry = $this->createTestAuthorizationEntry(self::TEST_ACCOUNT_ID);
        $entryXDR = $entry->toBase64Xdr();
        $decoded = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);

        $addrCreds = $decoded->credentials->addressCredentials;
        $this->assertNotNull($addrCreds);

        $address = $addrCreds->address;
        $this->assertInstanceOf(Address::class, $address);
        $this->assertSame(self::TEST_ACCOUNT_ID, $address->toStrKey());
    }

    // Helper methods

    /**
     * Create a test authorization entry using high-level SDK classes
     */
    private function createTestAuthorizationEntry(string $accountId, int $nonce = 12345): SorobanAuthorizationEntry
    {
        // Create address from account ID using high-level Address class
        $address = Address::fromAccountId($accountId);

        // Create address credentials with empty signature (will be filled by signer)
        $signature = XdrSCVal::forVoid();

        $addressCredentials = new SorobanAddressCredentials(
            $address,
            $nonce,
            0, // signatureExpirationLedger will be set by signer
            $signature
        );

        // Create credentials using high-level class
        $credentials = SorobanCredentials::forAddressCredentials($addressCredentials);

        // Create root invocation
        $rootInvocation = $this->createTestRootInvocation();

        return new SorobanAuthorizationEntry($credentials, $rootInvocation);
    }

    /**
     * Create a test root invocation using high-level SDK classes
     */
    private function createTestRootInvocation(): SorobanAuthorizedInvocation
    {
        // Create a contract address using high-level Address class
        $contractIdHex = hash('sha256', 'test_contract');
        $contractAddress = Address::fromContractId($contractIdHex);

        // Create authorized function using high-level class
        $function = SorobanAuthorizedFunction::forContractFunction($contractAddress, 'test_function', []);

        // Create invocation
        return new SorobanAuthorizedInvocation($function, []);
    }
}
