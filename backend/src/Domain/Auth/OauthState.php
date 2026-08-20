<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class OauthState
{
    public function __construct(
        public readonly string $state,
        public readonly string $expiresAt,
        public readonly ?string $redirectAfter,
        public readonly string $codeVerifier,
    ) {
    }
}
