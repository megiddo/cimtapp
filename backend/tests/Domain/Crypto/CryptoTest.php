<?php

declare(strict_types=1);

namespace Tests\Domain\Crypto;

use App\Domain\Crypto\Crypto;
use App\Domain\Crypto\CryptoException;
use App\Domain\Crypto\WrappedDek;
use Tests\TestCase;

class CryptoTest extends TestCase
{
    private const HEX_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const OTHER_HEX_KEY = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-crypto-');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testWrapUnwrapRoundTripWithHexMasterKey(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $this->assertSame(Crypto::DEK_BYTES, strlen($dek));

        $wrapped = $crypto->wrapDek($dek);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, strlen($wrapped->nonce()));
        $this->assertNotSame('', $wrapped->ciphertext());

        $opened = $crypto->unwrapDek($wrapped->nonce(), $wrapped->ciphertext());
        $this->assertSame($dek, $opened);
    }

    public function testWrapUsesFreshNonceEachTime(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $a = $crypto->wrapDek($dek);
        $b = $crypto->wrapDek($dek);
        $this->assertNotSame($a->nonce(), $b->nonce());
        $this->assertNotSame($a->ciphertext(), $b->ciphertext());
        $this->assertSame($dek, $crypto->unwrapDek($b->nonce(), $b->ciphertext()));
    }

    public function testMintDekIsUnique(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->assertNotSame($crypto->mintDek(), $crypto->mintDek());
    }

    public function testUppercaseHexMasterKeyRoundTrip(): void
    {
        $crypto = Crypto::fromMasterKey(strtoupper(self::HEX_KEY));
        $dek = $crypto->mintDek();
        $wrapped = $crypto->wrapDek($dek);
        $this->assertSame($dek, $crypto->unwrapDek($wrapped->nonce(), $wrapped->ciphertext()));
    }

    public function testBase64MasterKeyRoundTrip(): void
    {
        $encoded = base64_encode(random_bytes(32));
        $crypto = Crypto::fromMasterKey($encoded);
        $dek = $crypto->mintDek();
        $wrapped = $crypto->wrapDek($dek);
        $this->assertSame($dek, $crypto->unwrapDek($wrapped->nonce(), $wrapped->ciphertext()));
    }

    public function testWrongAmkFailsUnwrap(): void
    {
        $good = Crypto::fromMasterKey(self::HEX_KEY);
        $bad = Crypto::fromMasterKey(self::OTHER_HEX_KEY);
        $dek = $good->mintDek();
        $wrapped = $good->wrapDek($dek);

        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Unable to unwrap data key.');
        $bad->unwrapDek($wrapped->nonce(), $wrapped->ciphertext());
    }

    public function testTamperedCiphertextFailsUnwrap(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $wrapped = $crypto->wrapDek($crypto->mintDek());
        $tampered = $wrapped->ciphertext();
        $tampered[0] = $tampered[0] === "\0" ? "\x01" : "\0";

        $this->expectException(CryptoException::class);
        $crypto->unwrapDek($wrapped->nonce(), $tampered);
    }

    public function testWrongNonceFailsUnwrap(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $wrapped = $crypto->wrapDek($crypto->mintDek());
        $nonce = str_repeat("\0", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $this->expectException(CryptoException::class);
        $crypto->unwrapDek($nonce, $wrapped->ciphertext());
    }

    public function testShortNonceFailsUnwrapWithoutSodiumLeak(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $wrapped = $crypto->wrapDek($crypto->mintDek());

        try {
            $crypto->unwrapDek('short', $wrapped->ciphertext());
            $this->fail('Expected CryptoException');
        } catch (CryptoException $e) {
            $this->assertSame('Unable to unwrap data key.', $e->getMessage());
            $this->assertNull($e->getPrevious());
            $this->assertStringNotContainsString(self::HEX_KEY, $e->getMessage());
        }
    }

    public function testEmptyCiphertextFailsUnwrap(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->expectException(CryptoException::class);
        $crypto->unwrapDek(str_repeat("\0", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), '');
    }

    public function testWrapRejectsShortDek(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Invalid data key.');
        $crypto->wrapDek('too-short');
    }

    public function testExceptionsNeverIncludeKeyMaterial(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $wrapped = $crypto->wrapDek($dek);

        try {
            Crypto::fromMasterKey(self::OTHER_HEX_KEY)
                ->unwrapDek($wrapped->nonce(), $wrapped->ciphertext());
            $this->fail('Expected CryptoException');
        } catch (CryptoException $e) {
            $this->assertStringNotContainsString(self::HEX_KEY, $e->getMessage());
            $this->assertStringNotContainsString(self::OTHER_HEX_KEY, $e->getMessage());
            $this->assertStringNotContainsString(bin2hex($dek), $e->getMessage());
            $this->assertStringNotContainsString($dek, $e->getMessage());
            $this->assertStringNotContainsString($wrapped->ciphertext(), $e->getMessage());
        }
    }

    public function testDecodeMasterKeyRejectsEmptyAndGarbage(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Invalid master key.');
        Crypto::fromMasterKey('   ');
    }

    public function testDecodeMasterKeyRejectsShortBase64(): void
    {
        $this->expectException(CryptoException::class);
        Crypto::fromMasterKey(base64_encode('not-32-bytes'));
    }

    public function testDecodeMasterKeyRejectsMalformedBase64(): void
    {
        $this->expectException(CryptoException::class);
        Crypto::fromMasterKey('***not-base64***');
    }

    public function testFileEncryptDecryptRoundTripIncludingEmptyAndLarge(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();

        foreach (['', 'tiny', str_repeat('SQLite format 3 extra payload ', 400)] as $i => $payload) {
            $plain = $this->dir . "/plain-{$i}.bin";
            $enc = $this->dir . "/plain-{$i}.enc";
            $out = $this->dir . "/out-{$i}.bin";
            file_put_contents($plain, $payload);
            $crypto->encryptFile($plain, $enc, $dek);
            $this->assertFileExists($enc);
            $this->assertNotSame($payload, (string) file_get_contents($enc));
            $crypto->decryptFile($enc, $out, $dek);
            $this->assertSame($payload, (string) file_get_contents($out));
        }
    }

    public function testFileDecryptWrongDekFailsAndLeavesCiphertext(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $other = $crypto->mintDek();
        $plain = $this->dir . '/store.sqlite';
        $enc = $this->dir . '/store.sqlite.enc';
        $out = $this->dir . '/out.sqlite';
        file_put_contents($plain, 'user-db-bytes');
        $crypto->encryptFile($plain, $enc, $dek);
        $before = hash_file('sha256', $enc);

        try {
            $crypto->decryptFile($enc, $out, $other);
            $this->fail('Expected CryptoException');
        } catch (CryptoException $e) {
            $this->assertSame('Unable to decrypt store.', $e->getMessage());
            $this->assertStringNotContainsString(bin2hex($dek), $e->getMessage());
            $this->assertStringNotContainsString(bin2hex($other), $e->getMessage());
        }

        $this->assertSame($before, hash_file('sha256', $enc));
        $this->assertFileDoesNotExist($out);
    }

    public function testTamperedCiphertextFileFailsDecrypt(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $plain = $this->dir . '/a.bin';
        $enc = $this->dir . '/a.enc';
        file_put_contents($plain, str_repeat('x', 5000));
        $crypto->encryptFile($plain, $enc, $dek);
        $raw = (string) file_get_contents($enc);
        $pos = strlen($raw) - 10;
        $raw[$pos] = $raw[$pos] === "\0" ? "\x01" : "\0";
        file_put_contents($enc, $raw);

        $this->expectException(CryptoException::class);
        $crypto->decryptFile($enc, $this->dir . '/out.bin', $dek);
    }

    public function testTrailingGarbageOnCiphertextFailsDecrypt(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $plain = $this->dir . '/b.bin';
        $enc = $this->dir . '/b.enc';
        file_put_contents($plain, 'hello');
        $crypto->encryptFile($plain, $enc, $dek);
        file_put_contents($enc, (string) file_get_contents($enc) . "\x00");

        $this->expectException(CryptoException::class);
        $crypto->decryptFile($enc, $this->dir . '/out.bin', $dek);
    }

    public function testTruncatedCiphertextFailsDecrypt(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $plain = $this->dir . '/c.bin';
        $enc = $this->dir . '/c.enc';
        file_put_contents($plain, str_repeat('z', 100));
        $crypto->encryptFile($plain, $enc, $dek);
        $raw = (string) file_get_contents($enc);
        file_put_contents($enc, substr($raw, 0, 10));

        $this->expectException(CryptoException::class);
        $crypto->decryptFile($enc, $this->dir . '/out.bin', $dek);
    }

    public function testEncryptAndDecryptRejectDirectoryTargets(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $plain = $this->dir . '/p.bin';
        file_put_contents($plain, 'payload');
        $outDir = $this->dir . '/as-dir';
        mkdir($outDir);

        try {
            $crypto->encryptFile($plain, $outDir, $dek);
            $this->fail('Expected CryptoException');
        } catch (CryptoException $e) {
            $this->assertSame('Unable to encrypt store.', $e->getMessage());
        }

        $enc = $this->dir . '/p.enc';
        $crypto->encryptFile($plain, $enc, $dek);
        try {
            $crypto->decryptFile($enc, $outDir, $dek);
            $this->fail('Expected CryptoException');
        } catch (CryptoException $e) {
            $this->assertSame('Unable to decrypt store.', $e->getMessage());
        }
    }

    public function testHeaderOnlyCiphertextFailsDecrypt(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $dek = $crypto->mintDek();
        $plain = $this->dir . '/h.bin';
        $enc = $this->dir . '/h.enc';
        file_put_contents($plain, 'hello');
        $crypto->encryptFile($plain, $enc, $dek);
        $raw = (string) file_get_contents($enc);
        file_put_contents(
            $enc,
            substr($raw, 0, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES)
        );

        $this->expectException(CryptoException::class);
        $crypto->decryptFile($enc, $this->dir . '/hout.bin', $dek);
    }

    public function testEncryptMissingFileFails(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Unable to read store file.');
        $crypto->encryptFile($this->dir . '/missing.bin', $this->dir . '/x.enc', $crypto->mintDek());
    }

    public function testDecryptMissingFileFails(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->expectException(CryptoException::class);
        $crypto->decryptFile($this->dir . '/missing.enc', $this->dir . '/x.bin', $crypto->mintDek());
    }

    public function testEncryptRejectsShortDek(): void
    {
        $crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $plain = $this->dir . '/p.bin';
        file_put_contents($plain, 'x');
        $this->expectException(CryptoException::class);
        $crypto->encryptFile($plain, $this->dir . '/p.enc', 'short');
    }

    public function testWrappedDekRejectsShortNonce(): void
    {
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('Invalid wrapped data key.');
        new WrappedDek('nope', 'ciphertext');
    }

    public function testWrappedDekRejectsEmptyCiphertext(): void
    {
        $this->expectException(CryptoException::class);
        new WrappedDek(str_repeat("\0", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), '');
    }

    public function testRewrapOpensWithNewAmkAndNotTheOld(): void
    {
        $old = Crypto::fromMasterKey(self::HEX_KEY);
        $new = Crypto::fromMasterKey(self::OTHER_HEX_KEY);
        $dek = $old->mintDek();
        $wrapped = $old->wrapDek($dek);

        $rewrapped = $old->rewrapDek($wrapped->nonce(), $wrapped->ciphertext(), $new);
        $this->assertNotSame($wrapped->nonce(), $rewrapped->nonce());
        $this->assertNotSame($wrapped->ciphertext(), $rewrapped->ciphertext());
        $this->assertSame($dek, $new->unwrapDek($rewrapped->nonce(), $rewrapped->ciphertext()));

        $this->expectException(CryptoException::class);
        $old->unwrapDek($rewrapped->nonce(), $rewrapped->ciphertext());
    }

    public function testRewrapFileCiphertextStillOpensWithSameDek(): void
    {
        $old = Crypto::fromMasterKey(self::HEX_KEY);
        $new = Crypto::fromMasterKey(self::OTHER_HEX_KEY);
        $dek = $old->mintDek();
        $plain = $this->dir . '/store.bin';
        $enc = $this->dir . '/store.enc';
        $out = $this->dir . '/store-out.bin';
        file_put_contents($plain, 'user-sqlite-bytes');
        $old->encryptFile($plain, $enc, $dek);
        $before = hash_file('sha256', $enc);

        $wrapped = $old->wrapDek($dek);
        $rewrapped = $old->rewrapDek($wrapped->nonce(), $wrapped->ciphertext(), $new);
        $opened = $new->unwrapDek($rewrapped->nonce(), $rewrapped->ciphertext());
        $this->assertSame($dek, $opened);
        $new->decryptFile($enc, $out, $opened);
        $this->assertSame('user-sqlite-bytes', (string) file_get_contents($out));
        $this->assertSame($before, hash_file('sha256', $enc));
    }
}
