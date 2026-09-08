<?php

declare(strict_types=1);

namespace App\Application\Settings;

use InvalidArgumentException;

final class GoogleEnvGuard
{
    public function __construct(private readonly EnvValidator $env)
    {
    }

    /**
     * @param array<string, mixed> $values
     */
    public function assertConfigured(array $values): void
    {
        foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'] as $name) {
            if ($this->env->read($values, $name) === '') {
                throw new InvalidArgumentException($name . ' is required in production.');
            }
        }

        $redirect = $this->env->read($values, 'GOOGLE_REDIRECT_URI');
        if (!$this->env->isValidAppUrl($redirect)) {
            throw new InvalidArgumentException('GOOGLE_REDIRECT_URI must be an absolute http(s) URL.');
        }
    }
}
