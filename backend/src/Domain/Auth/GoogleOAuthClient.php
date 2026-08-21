<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface GoogleOAuthClient
{
    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $codeChallenge): string;

    /**
     * Exchange an authorization code (server-side) and load Google userinfo.
     *
     * @throws GoogleOAuthException
     */
    public function fetchUser(string $code, string $codeVerifier): GoogleUserInfo;
}
