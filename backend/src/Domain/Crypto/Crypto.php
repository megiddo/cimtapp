<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

/**
 * AMK-wrapped DEKs (secretbox) and DEK-wrapped sqlite files (secretstream).
 * Never logs key material.
 */
final class Crypto
{
    public const DEK_BYTES = 32;

    private readonly DekWrapper $deks;
    private readonly SecretstreamFileCipher $files;

    private function __construct(string $amk)
    {
        $this->deks = new DekWrapper($amk);
        $this->files = new SecretstreamFileCipher($this->deks);
    }

    public static function fromMasterKey(string $encoded): self
    {
        return new self(self::decodeMasterKey($encoded));
    }

    public static function decodeMasterKey(string $encoded): string
    {
        $encoded = trim($encoded);
        if (preg_match('/^[0-9a-fA-F]{64}$/', $encoded) === 1) {
            return sodium_hex2bin($encoded);
        }

        $bytes = base64_decode($encoded, true);
        if (!is_string($bytes) || strlen($bytes) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new CryptoException('Invalid master key.');
        }

        return $bytes;
    }

    public function mintDek(): string
    {
        return $this->deks->mintDek();
    }

    public function wrapDek(string $dek): WrappedDek
    {
        return $this->deks->wrap($dek);
    }

    public function unwrapDek(string $nonce, string $ciphertext): string
    {
        return $this->deks->unwrap($nonce, $ciphertext);
    }

    /**
     * Unwrap a DEK with this AMK and wrap it with $newCrypto's AMK.
     * File ciphertext is unchanged; only users.encrypted_dek / dek_nonce rotate.
     */
    public function rewrapDek(string $nonce, string $ciphertext, self $newCrypto): WrappedDek
    {
        return $this->deks->rewrap($nonce, $ciphertext, $newCrypto->deks);
    }

    public function encryptFile(string $plaintextPath, string $ciphertextPath, string $dek): void
    {
        $this->files->encryptFile($plaintextPath, $ciphertextPath, $dek);
    }

    public function decryptFile(string $ciphertextPath, string $plaintextPath, string $dek): void
    {
        $this->files->decryptFile($ciphertextPath, $plaintextPath, $dek);
    }
}
