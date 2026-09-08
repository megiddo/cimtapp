<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Reads AuthContext without opening the user store. Export/restore stay on Action.
 */
final class RequireAuthContext
{
    public function from(Request $request): AuthContext
    {
        $context = $request->getAttribute(AuthContext::class);
        if (!$context instanceof AuthContext) {
            throw new HttpUnauthorizedException($request, AuthConfig::AUTH_REQUIRED);
        }

        return $context;
    }
}
