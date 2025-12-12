<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Signer;

use InvalidArgumentException;
use RuntimeException;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Soroban\SorobanAuthorizationEntry;
use Soneso\StellarSDK\Soroban\SorobanServer;

/**
 * SEP-45 authorization entry signer for Stellar Web Authentication for Contract Accounts
 *
 * This class provides functionality to sign a single SEP-45 authorization entry
 * using the Stellar PHP SDK's high-level SorobanAuthorizationEntry API.
 * SEP-45 defines a standard protocol for contract account authentication
 * on the Stellar network.
 *
 * @see https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0045.md
 */
class Sep45Signer
{
    /**
     * Signs a SEP-45 authorization entry
     *
     * Takes a base64-encoded XDR of a single SorobanAuthorizationEntry, fetches the current
     * ledger from Soroban RPC using the PHP SDK's SorobanServer, signs the entry
     * using the SDK's SorobanAuthorizationEntry.sign() method, and returns the
     * signed entry as base64 XDR.
     *
     * @param string $entryXDR Base64-encoded XDR of a single SorobanAuthorizationEntry
     * @param string $networkPassphrase Network passphrase for signing
     * @param string $secretKey Secret seed (S...) of the signing key
     * @param string $sorobanRpcUrl Soroban RPC endpoint URL
     * @return string Base64-encoded XDR of the signed authorization entry
     * @throws InvalidArgumentException If inputs are invalid or XDR cannot be decoded
     * @throws RuntimeException If RPC call fails, signing fails, or entry address doesn't match signing key
     */
    public static function sign(
        string $entryXDR,
        string $networkPassphrase,
        string $secretKey,
        string $sorobanRpcUrl
    ): string {
        // Validate inputs
        if (empty($entryXDR)) {
            throw new InvalidArgumentException('authorization_entry XDR cannot be empty');
        }
        if (empty($networkPassphrase)) {
            throw new InvalidArgumentException('network passphrase cannot be empty');
        }
        if (empty($secretKey)) {
            throw new InvalidArgumentException('secret key cannot be empty');
        }
        if (empty($sorobanRpcUrl)) {
            throw new InvalidArgumentException('soroban RPC URL cannot be empty');
        }

        // Parse the keypair from secret key
        try {
            $keyPair = KeyPair::fromSeed($secretKey);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('failed to parse secret key: ' . $e->getMessage(), 0, $e);
        }

        // Verify keypair has private key
        if ($keyPair->getPrivateKey() === null) {
            throw new InvalidArgumentException('secret key is not a full keypair');
        }

        $signingAccount = $keyPair->getAccountId();

        // Create Network object for signing
        $network = new Network($networkPassphrase);

        // Decode base64 XDR of single SorobanAuthorizationEntry
        try {
            $entry = SorobanAuthorizationEntry::fromBase64Xdr($entryXDR);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('failed to decode authorization entry: ' . $e->getMessage(), 0, $e);
        }

        // Validate that the entry uses address credentials
        $credentials = $entry->credentials;
        if ($credentials->addressCredentials === null) {
            throw new InvalidArgumentException('entry must use address credentials');
        }

        // Get the entry address and verify it matches the signing key
        $address = $credentials->addressCredentials->address;
        try {
            $entryAccount = $address->toStrKey();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('failed to get entry address: ' . $e->getMessage(), 0, $e);
        }

        if ($entryAccount !== $signingAccount) {
            throw new InvalidArgumentException('entry address does not match signing key');
        }

        // Fetch current ledger from Soroban RPC
        $currentLedger = self::getLatestLedger($sorobanRpcUrl);
        $signatureExpirationLedger = $currentLedger + 10;

        // Set signature expiration ledger
        $credentials->addressCredentials->signatureExpirationLedger = $signatureExpirationLedger;

        // Sign using the SDK's high-level sign() method
        try {
            $entry->sign($keyPair, $network);
        } catch (\Throwable $e) {
            throw new RuntimeException('failed to sign entry: ' . $e->getMessage(), 0, $e);
        }

        // Encode entry back to base64 XDR
        return $entry->toBase64Xdr();
    }

    /**
     * Fetches the latest ledger sequence from Soroban RPC using the PHP SDK's SorobanServer
     *
     * @param string $rpcUrl Soroban RPC endpoint URL
     * @return int Current ledger sequence number
     * @throws RuntimeException If RPC call fails or sequence is not available
     */
    private static function getLatestLedger(string $rpcUrl): int
    {
        try {
            $server = new SorobanServer($rpcUrl);
            $response = $server->getLatestLedger();

            if ($response->error !== null) {
                $errorMsg = $response->error->message ?? 'unknown error';
                throw new RuntimeException("RPC error: {$errorMsg}");
            }

            if ($response->sequence === null) {
                throw new RuntimeException('RPC response missing sequence field');
            }

            return $response->sequence;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('failed to call Soroban RPC: ' . $e->getMessage(), 0, $e);
        }
    }
}
