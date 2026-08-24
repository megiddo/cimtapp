<?php

declare(strict_types=1);

namespace App\Infrastructure\OAuth;

use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\GoogleUserInfo;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Throwable;

final class LeagueGoogleOAuthClient implements GoogleOAuthClient
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly Google $provider,
        private readonly bool $configured,
    ) {
    }

    public static function fromCredentials(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        ?ClientInterface $httpClient = null,
    ): self {
        $collaborators = [];
        if ($httpClient !== null) {
            $collaborators['httpClient'] = $httpClient;
        }

        return new self(
            new Google([
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'redirectUri' => $redirectUri,
            ], $collaborators),
            $clientId !== '' && $clientSecret !== '' && $redirectUri !== '',
        );
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return $this->provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => ['openid', 'email'],
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function fetchUser(string $code, string $codeVerifier): GoogleUserInfo
    {
        if (!$this->configured) {
            throw new GoogleOAuthException('Google sign-in is not configured.');
        }
        if ($code === '' || $codeVerifier === '') {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        try {
            $this->provider->setPkceCode($codeVerifier);
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
            if (!$token instanceof AccessToken) {
                throw new GoogleOAuthException('Unable to sign in with Google.');
            }
            $owner = $this->provider->getResourceOwner($token);
        } catch (Throwable) {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        if (!$owner instanceof GoogleUser) {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        $profile = $owner->toArray();
        $sub = $profile['sub'] ?? null;
        $email = $owner->getEmail();
        if (!is_string($sub) || $sub === '' || !is_string($email) || $email === '') {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        return new GoogleUserInfo($sub, $email, $this->isVerified($profile['email_verified'] ?? false));
    }

    private function isVerified(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
