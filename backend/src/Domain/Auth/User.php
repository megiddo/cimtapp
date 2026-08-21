<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $passwordHash,
        public readonly ?string $googleSub,
        public readonly string $encryptedDek,
        public readonly string $dekNonce,
        public readonly string $createdAt,
        public readonly ?string $lastLoginAt,
    ) {
    }

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null && $this->passwordHash !== '';
    }

    public function hasGoogle(): bool
    {
        return $this->googleSub !== null && $this->googleSub !== '';
    }

    /**
     * Identity for GET /me. Never includes DEK material.
     *
     * @return array{email: string, has_password: bool, has_google: bool, remainder: null}
     */
    public function toMeArray(): array
    {
        return [
            'email' => $this->email,
            'has_password' => $this->hasPassword(),
            'has_google' => $this->hasGoogle(),
            'remainder' => null,
        ];
    }
}
