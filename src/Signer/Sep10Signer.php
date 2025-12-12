<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Signer;

use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Transaction;

/**
 * SEP-10 transaction signer for Stellar Web Authentication
 *
 * This class provides functionality to sign SEP-10 authentication transactions
 * using the Stellar PHP SDK. SEP-10 defines a standard protocol for account
 * authentication on the Stellar network.
 *
 * @see https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md
 */
class Sep10Signer
{
    /**
     * Signs a SEP-10 transaction envelope
     *
     * Takes a base64-encoded transaction XDR, validates it's a regular transaction
     * (not a fee bump), signs it with the provided secret key, and returns the
     * signed transaction as base64 XDR.
     *
     * @param string $transactionXDR Base64-encoded XDR transaction envelope
     * @param string $networkPassphrase Network passphrase for the target network
     * @param string $secretKey Secret seed (S...) of the signing key
     * @return string Base64-encoded XDR of the signed transaction envelope
     * @throws \InvalidArgumentException If transaction XDR is invalid, not a regular transaction, or secret key is invalid
     * @throws \Exception If signing fails
     */
    public static function sign(string $transactionXDR, string $networkPassphrase, string $secretKey): string
    {
        // Validate inputs
        if (empty($transactionXDR)) {
            throw new \InvalidArgumentException('transaction XDR cannot be empty');
        }
        if (empty($networkPassphrase)) {
            throw new \InvalidArgumentException('network passphrase cannot be empty');
        }
        if (empty($secretKey)) {
            throw new \InvalidArgumentException('secret key cannot be empty');
        }

        // Parse the keypair from secret key
        try {
            $keyPair = KeyPair::fromSeed($secretKey);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('failed to parse secret key: ' . $e->getMessage(), 0, $e);
        }

        // Verify keypair has private key
        if ($keyPair->getPrivateKey() === null) {
            throw new \InvalidArgumentException('secret key is not a full keypair');
        }

        // Parse the transaction envelope from XDR
        try {
            $abstractTx = AbstractTransaction::fromEnvelopeBase64XdrString($transactionXDR);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('failed to parse transaction XDR: ' . $e->getMessage(), 0, $e);
        }

        // Validate it's a regular transaction, not a fee bump
        if (!($abstractTx instanceof Transaction)) {
            throw new \InvalidArgumentException('expected a regular transaction, not a fee bump transaction');
        }

        // Create network instance
        $network = new Network($networkPassphrase);

        // Sign the transaction
        try {
            $abstractTx->sign($keyPair, $network);
        } catch (\Throwable $e) {
            throw new \Exception('failed to sign transaction: ' . $e->getMessage(), 0, $e);
        }

        // Convert back to base64 XDR
        try {
            return $abstractTx->toEnvelopeXdrBase64();
        } catch (\Throwable $e) {
            throw new \Exception('failed to encode signed transaction: ' . $e->getMessage(), 0, $e);
        }
    }
}
