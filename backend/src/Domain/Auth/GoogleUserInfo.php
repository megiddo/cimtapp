<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class GoogleUserInfo
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailVerified,
    ) {
    }
}
