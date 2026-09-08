<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

use SodiumException;

final class SecretstreamFileCipher
{
    private const FILE_CHUNK_BYTES = 4096;

    public function __construct(private readonly DekWrapper $deks)
    {
    }

    public function encryptFile(string $plaintextPath, string $ciphertextPath, string $dek): void
    {
        $this->deks->assertDek($dek);
        $this->assertReadableFile($plaintextPath);
        $this->copyThroughSecretstream($plaintextPath, $ciphertextPath, $dek, true);
    }

    public function decryptFile(string $ciphertextPath, string $plaintextPath, string $dek): void
    {
        $this->deks->assertDek($dek);
        $this->assertReadableFile($ciphertextPath);

        try {
            $this->copyThroughSecretstream($ciphertextPath, $plaintextPath, $dek, false);
        } catch (CryptoException $e) {
            $this->unlinkIfExists($plaintextPath);
            throw $e;
        }
    }

    private function copyThroughSecretstream(
        string $inPath,
        string $outPath,
        string $dek,
        bool $encrypt,
    ): void {
        $in = @fopen($inPath, 'rb');
        $out = @fopen($outPath, 'wb');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            throw new CryptoException($encrypt ? 'Unable to encrypt store.' : 'Unable to decrypt store.');
        }

        try {
            if ($encrypt) {
                $this->encryptStream($in, $out, $dek);
            } else {
                $this->decryptStream($in, $out, $dek);
            }
        } catch (SodiumException) {
            throw new CryptoException($encrypt ? 'Unable to encrypt store.' : 'Unable to decrypt store.');
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    private function encryptStream($in, $out, string $dek): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($dek);
        fwrite($out, $header);

        do {
            $chunk = fread($in, self::FILE_CHUNK_BYTES);
            if ($chunk === false) {
                throw new CryptoException('Unable to encrypt store.');
            }
            $eof = feof($in);
            $tag = $eof
                ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
        } while (!$eof);
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    private function decryptStream($in, $out, string $dek): void
    {
        $headerBytes = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        $header = fread($in, $headerBytes);
        if ($header === false || strlen($header) !== $headerBytes) {
            throw new CryptoException('Unable to decrypt store.');
        }

        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $dek);
        $encChunk = self::FILE_CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

        do {
            $chunk = fread($in, $encChunk);
            if ($chunk === false || $chunk === '') {
                throw new CryptoException('Unable to decrypt store.');
            }

            $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($pulled === false) {
                throw new CryptoException('Unable to decrypt store.');
            }

            [$plain, $tag] = $pulled;
            fwrite($out, $plain);
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    }

    private function assertReadableFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CryptoException('Unable to read store file.');
        }
    }

    private function unlinkIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
