<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\GoogleUserInfo;

final class FakeGoogleOAuthClient implements GoogleOAuthClient
{
    public bool $configured = true;

    public ?GoogleUserInfo $user = null;

    public string $lastState = '';

    public string $lastChallenge = '';

    public string $lastCode = '';

    public string $lastVerifier = '';

    public bool $throwOnFetch = false;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $this->lastState = $state;
        $this->lastChallenge = $codeChallenge;

        return 'https://accounts.example.test/o/oauth2/v2/auth?state='
            . rawurlencode($state)
            . '&code_challenge='
            . rawurlencode($codeChallenge)
            . '&code_challenge_method=S256';
    }

    public function fetchUser(string $code, string $codeVerifier): GoogleUserInfo
    {
        $this->lastCode = $code;
        $this->lastVerifier = $codeVerifier;
        if ($this->throwOnFetch || $this->user === null) {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        return $this->user;
    }
}
