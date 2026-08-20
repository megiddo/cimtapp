<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

final class WrappedDek
{
    public function __construct(
        private readonly string $nonce,
        private readonly string $ciphertext,
    ) {
        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new CryptoException('Invalid wrapped data key.');
        }
        if ($ciphertext === '') {
            throw new CryptoException('Invalid wrapped data key.');
        }
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }
}
