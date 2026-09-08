<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

final class DekWrapper
{
    public function __construct(private readonly string $amk)
    {
    }

    public function wrap(string $dek): WrappedDek
    {
        $this->assertDek($dek);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($dek, $nonce, $this->amk);

        return new WrappedDek($nonce, $ciphertext);
    }

    public function unwrap(string $nonce, string $ciphertext): string
    {
        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || $ciphertext === '') {
            throw new CryptoException('Unable to unwrap data key.');
        }

        $dek = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->amk);
        if (!is_string($dek) || strlen($dek) !== Crypto::DEK_BYTES) {
            throw new CryptoException('Unable to unwrap data key.');
        }

        return $dek;
    }

    public function rewrap(string $nonce, string $ciphertext, self $newWrapper): WrappedDek
    {
        return $newWrapper->wrap($this->unwrap($nonce, $ciphertext));
    }

    public function mintDek(): string
    {
        return random_bytes(Crypto::DEK_BYTES);
    }

    public function assertDek(string $dek): void
    {
        if (strlen($dek) !== Crypto::DEK_BYTES) {
            throw new CryptoException('Invalid data key.');
        }
    }
}
