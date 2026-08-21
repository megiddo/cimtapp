<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class AuthContext
{
    public function __construct(
        public readonly User $user,
        public readonly string $dek,
        public readonly Session $session,
    ) {
    }
}
