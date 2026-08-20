<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\GoogleUserInfo;

final class HttpGoogleOAuthClient implements GoogleOAuthClient
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly HttpTransport $http,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->redirectUri !== '';
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTH_URL . '?' . $query;
    }

    public function fetchUser(string $code, string $codeVerifier): GoogleUserInfo
    {
        if (!$this->isConfigured()) {
            throw new GoogleOAuthException('Google sign-in is not configured.');
        }
        if ($code === '' || $codeVerifier === '') {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        $tokenBody = $this->http->request(
            'POST',
            self::TOKEN_URL,
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
            http_build_query([
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ], '', '&', PHP_QUERY_RFC3986)
        );

        $token = $this->decodeJson($tokenBody);
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        $userBody = $this->http->request(
            'GET',
            self::USERINFO_URL,
            ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'],
        );
        $profile = $this->decodeJson($userBody);
        $sub = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        if (!is_string($sub) || $sub === '' || !is_string($email) || $email === '') {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        return new GoogleUserInfo($sub, $email, $this->isVerified($profile['email_verified'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }
        if (!is_array($decoded)) {
            throw new GoogleOAuthException('Unable to sign in with Google.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function isVerified(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
