<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $expiresAt,
        public readonly string $createdAt,
    ) {
    }

    public function withExpiresAt(string $expiresAt): self
    {
        return new self($this->id, $this->userId, $expiresAt, $this->createdAt);
    }
}
