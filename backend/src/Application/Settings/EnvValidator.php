<?php

declare(strict_types=1);

namespace App\Application\Settings;

use InvalidArgumentException;

/**
 * Validates process environment for boot. Google OAuth secrets are optional
 * outside production so unit tests never need real credentials.
 */
final class EnvValidator
{
    public const MASTER_KEY_HEX_LENGTH = 64;
    public const MASTER_KEY_BYTES = 32;

    /**
     * @param array<string, mixed> $env
     * @return array{
     *     appEnv: string,
     *     masterKey: string,
     *     dataDir: string,
     *     appUrl: string,
     *     sessionSecure: bool,
     *     googleClientId: string,
     *     googleClientSecret: string,
     *     googleRedirectUri: string
     * }
     */
    public function validate(array $env): array
    {
        $appEnv = $this->read($env, 'APP_ENV', 'development');
        if (!$this->isKnownAppEnv($appEnv)) {
            throw new InvalidArgumentException(
                'APP_ENV must be one of: testing, development, production.'
            );
        }

        $masterKey = $this->read($env, 'CIMT_MASTER_KEY');
        if (!$this->isValidMasterKey($masterKey)) {
            throw new InvalidArgumentException(
                'CIMT_MASTER_KEY must be a 256-bit key: 64 hex characters or base64 encoding of 32 bytes.'
            );
        }

        $dataDir = trim($this->read($env, 'DATA_DIR', dirname(__DIR__, 4) . '/data'));
        if ($dataDir === '' || !$this->isSafePath($dataDir)) {
            throw new InvalidArgumentException('DATA_DIR must be a non-empty absolute or relative path.');
        }

        $appUrl = $this->read($env, 'APP_URL', 'http://localhost:24780');
        if (!$this->isValidAppUrl($appUrl)) {
            throw new InvalidArgumentException('APP_URL must be an absolute http(s) URL.');
        }

        $sessionSecure = $this->isTruthy($this->read($env, 'SESSION_SECURE', 'false'));

        if ($this->requiresGoogleOAuth($appEnv)) {
            $this->assertGoogleConfigured($env);
        }

        $googleClientId = $this->read($env, 'GOOGLE_CLIENT_ID');
        $googleClientSecret = $this->read($env, 'GOOGLE_CLIENT_SECRET');
        $googleRedirectUri = $this->read($env, 'GOOGLE_REDIRECT_URI');

        return [
            'appEnv' => $appEnv,
            'masterKey' => $masterKey,
            'dataDir' => $dataDir,
            'appUrl' => rtrim($appUrl, '/'),
            'sessionSecure' => $sessionSecure,
            'googleClientId' => $googleClientId,
            'googleClientSecret' => $googleClientSecret,
            'googleRedirectUri' => $googleRedirectUri,
        ];
    }

    public function isKnownAppEnv(string $appEnv): bool
    {
        return in_array($appEnv, ['testing', 'development', 'production'], true);
    }

    public function requiresGoogleOAuth(string $appEnv): bool
    {
        return $appEnv === 'production';
    }

    public function isValidMasterKey(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        if (preg_match('/^[0-9a-fA-F]{' . self::MASTER_KEY_HEX_LENGTH . '}$/', $key) === 1) {
            return true;
        }

        $decoded = base64_decode($key, true);
        if ($decoded === false) {
            return false;
        }

        return strlen($decoded) === self::MASTER_KEY_BYTES;
    }

    public function isValidAppUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === 'http' || $scheme === 'https';
    }

    public function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function isSafePath(string $path): bool
    {
        return $path !== '' && !str_contains($path, "\0");
    }

    /**
     * @param array<string, mixed> $env
     */
    public function assertGoogleConfigured(array $env): void
    {
        foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'] as $name) {
            if ($this->read($env, $name) === '') {
                throw new InvalidArgumentException($name . ' is required in production.');
            }
        }

        $redirect = $this->read($env, 'GOOGLE_REDIRECT_URI');
        if (!$this->isValidAppUrl($redirect)) {
            throw new InvalidArgumentException('GOOGLE_REDIRECT_URI must be an absolute http(s) URL.');
        }
    }

    /**
     * @param array<string, mixed> $env
     */
    private function read(array $env, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $env)) {
            return $default;
        }

        $value = $env[$key];
        if ($value === false) {
            return 'false';
        }
        if ($value === null || $value === '') {
            return $default;
        }

        if ($value === true) {
            return 'true';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        return $value;
    }
}
