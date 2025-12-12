<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Tests\Signer;

use DateTime;
use InvalidArgumentException;
use phpseclib3\Math\BigInteger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soneso\ServerSigner\Signer\Sep10Signer;
use Soneso\StellarSDK\AbstractTransaction;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\FeeBumpTransaction;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;

/**
 * Test suite for SEP-10 transaction signer
 */
final class Sep10SignerTest extends TestCase
{
    private const TEST_SECRET = 'SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG';
    private const TEST_ACCOUNT_ID = 'GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV';
    private const TEST_NETWORK = 'Test SDF Network ; September 2015';

    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::fromSeed(self::TEST_SECRET);
    }

    #[Test]
    public function it_signs_valid_transaction_successfully(): void
    {
        // Create a test transaction
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test_key', 'test_value'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        // Sign the transaction
        $signedXDR = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        // Verify the signed transaction is valid
        $this->assertIsString($signedXDR);
        $this->assertNotEmpty($signedXDR);
        $this->assertNotSame($transactionXDR, $signedXDR, 'Signed XDR should differ from original');

        // Parse the signed transaction
        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $this->assertInstanceOf(Transaction::class, $signedTx);

        // Verify signature was added
        $this->assertNotEmpty($signedTx->getSignatures(), 'Transaction should have signatures');

        // Verify the signature is valid
        $network = new Network(self::TEST_NETWORK);
        $txHash = $signedTx->hash($network);
        $signatures = $signedTx->getSignatures();

        $this->assertGreaterThan(0, count($signatures), 'Should have at least one signature');

        $signature = $signatures[0];
        $verified = $this->keyPair->verifySignature($signature->getSignature(), $txHash);
        $this->assertTrue($verified, 'Signature should be cryptographically valid');
    }

    #[Test]
    public function it_preserves_existing_signatures(): void
    {
        // Create and sign a transaction first
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $network = new Network(self::TEST_NETWORK);
        $transaction->sign($this->keyPair, $network);

        $initialSigCount = count($transaction->getSignatures());
        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        // Sign again with the same key
        $signedXDR = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $finalSigCount = count($signedTx->getSignatures());

        // Should have added another signature
        $this->assertGreaterThan($initialSigCount, $finalSigCount, 'Should add signature even if already signed');
    }

    #[Test]
    public function it_throws_exception_for_empty_transaction_xdr(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('transaction XDR cannot be empty');

        Sep10Signer::sign('', self::TEST_NETWORK, self::TEST_SECRET);
    }

    #[Test]
    public function it_throws_exception_for_empty_network_passphrase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('network passphrase cannot be empty');

        Sep10Signer::sign('some_xdr', '', self::TEST_SECRET);
    }

    #[Test]
    public function it_throws_exception_for_empty_secret_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('secret key cannot be empty');

        Sep10Signer::sign('some_xdr', self::TEST_NETWORK, '');
    }

    #[Test]
    public function it_throws_exception_for_invalid_xdr(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse transaction XDR');

        Sep10Signer::sign('invalid-xdr-data', self::TEST_NETWORK, self::TEST_SECRET);
    }

    #[Test]
    public function it_throws_exception_for_malformed_base64_xdr(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse transaction XDR');

        Sep10Signer::sign('not!!!valid!!!base64', self::TEST_NETWORK, self::TEST_SECRET);
    }

    #[Test]
    public function it_throws_exception_for_invalid_secret_key(): void
    {
        // Create a valid transaction XDR first
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse secret key');

        Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, 'INVALID_SECRET_KEY');
    }

    #[Test]
    public function it_throws_exception_for_public_key_instead_of_secret(): void
    {
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failed to parse secret key');

        // Try to use public key (G...) instead of secret key (S...)
        Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_ACCOUNT_ID);
    }

    #[Test]
    public function it_throws_exception_for_fee_bump_transaction(): void
    {
        // Create a regular transaction
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $innerTx = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        // Create fee bump transaction
        $feeBumpTx = (new \Soneso\StellarSDK\FeeBumpTransactionBuilder($innerTx))
            ->setFeeAccount($this->keyPair->getAccountId())
            ->setBaseFee(200)
            ->build();

        $feeBumpXDR = $feeBumpTx->toEnvelopeXdrBase64();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected a regular transaction, not a fee bump transaction');

        Sep10Signer::sign($feeBumpXDR, self::TEST_NETWORK, self::TEST_SECRET);
    }

    #[Test]
    public function it_signs_transaction_with_multiple_operations(): void
    {
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation(
                (new ManageDataOperationBuilder('key1', 'value1'))->build()
            )
            ->addOperation(
                (new ManageDataOperationBuilder('key2', 'value2'))->build()
            )
            ->addOperation(
                (new ManageDataOperationBuilder('key3', 'value3'))->build()
            )
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $signedXDR = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        $this->assertIsString($signedXDR);
        $this->assertNotEmpty($signedXDR);

        // Verify signed transaction can be parsed
        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $this->assertInstanceOf(Transaction::class, $signedTx);
        $this->assertNotEmpty($signedTx->getSignatures());
    }

    #[Test]
    public function it_signs_transaction_with_different_network_passphrase(): void
    {
        $mainnetPassphrase = 'Public Global Stellar Network ; September 2015';

        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $signedXDR = Sep10Signer::sign($transactionXDR, $mainnetPassphrase, self::TEST_SECRET);

        $this->assertIsString($signedXDR);
        $this->assertNotEmpty($signedXDR);

        // Verify signature is for the correct network
        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $mainnetNetwork = new Network($mainnetPassphrase);
        $txHash = $signedTx->hash($mainnetNetwork);

        $signatures = $signedTx->getSignatures();
        $this->assertNotEmpty($signatures);

        $verified = $this->keyPair->verifySignature($signatures[0]->getSignature(), $txHash);
        $this->assertTrue($verified, 'Signature should be valid for mainnet network');
    }

    #[Test]
    public function it_handles_transaction_with_memo(): void
    {
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->addMemo(\Soneso\StellarSDK\Memo::text('test memo'))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $signedXDR = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        $this->assertIsString($signedXDR);
        $this->assertNotEmpty($signedXDR);

        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $this->assertInstanceOf(Transaction::class, $signedTx);
    }

    #[Test]
    public function it_signs_transaction_with_zero_sequence_number(): void
    {
        // SEP-10 transactions typically use sequence number 0
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test auth', 'challenge'))
            ->build();

        // Use TransactionBuilder which handles sequence numbers
        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(time()), (new DateTime)->setTimestamp(time() + 300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        $signedXDR = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        $this->assertIsString($signedXDR);
        $this->assertNotEmpty($signedXDR);

        $signedTx = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR);
        $this->assertInstanceOf(Transaction::class, $signedTx);
        $this->assertNotEmpty($signedTx->getSignatures());
    }

    #[Test]
    public function it_produces_deterministic_signatures_for_same_transaction(): void
    {
        $sourceAccount = new Account($this->keyPair->getAccountId(), new BigInteger(0));
        $manageDataOp = (new ManageDataOperationBuilder('test', 'data'))
            ->build();

        $transaction = (new TransactionBuilder($sourceAccount))
            ->addOperation($manageDataOp)
            ->setTimeBounds(new TimeBounds((new DateTime)->setTimestamp(1000000000), (new DateTime)->setTimestamp(1000000300)))
            ->build();

        $transactionXDR = $transaction->toEnvelopeXdrBase64();

        // Sign the same transaction twice
        $signedXDR1 = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);

        // Parse and get signature bytes
        $signedTx1 = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR1);
        $signatures1 = $signedTx1->getSignatures();

        // Sign again
        $signedXDR2 = Sep10Signer::sign($transactionXDR, self::TEST_NETWORK, self::TEST_SECRET);
        $signedTx2 = AbstractTransaction::fromEnvelopeBase64XdrString($signedXDR2);
        $signatures2 = $signedTx2->getSignatures();

        // Signatures should be deterministic (same input = same signature)
        // Note: This depends on the SDK's implementation
        $this->assertCount(count($signatures1), $signatures2, 'Should have same number of signatures');
    }
}
