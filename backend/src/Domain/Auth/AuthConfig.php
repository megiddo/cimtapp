<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Auth product rules. Password minimum is 12 characters (document in DESIGN.md).
 * Sessions use a 14-day sliding expiry: each authenticated request extends expires_at.
 */
final class AuthConfig
{
    public const PASSWORD_MIN_LENGTH = 12;

    public const SESSION_COOKIE = 'cimtapp_session';

    /** 14 days. Sliding: middleware refreshes expires_at on each authenticated request. */
    public const SESSION_TTL_SECONDS = 14 * 24 * 60 * 60;

    public const OAUTH_STATE_TTL_SECONDS = 10 * 60;

    public const DEFAULT_SYRINGE_LABEL = '0.5 mL / 50 IU';

    public const DEFAULT_SYRINGE_VOLUME_ML = 0.5;

    public const DEFAULT_SYRINGE_CAPACITY_IU = 50.0;

    public const GENERIC_LOGIN_ERROR = 'Invalid email or password';

    public const EMAIL_TAKEN = 'Email is already registered.';

    public const EMAIL_INVALID = 'Enter a valid email address.';

    public const PASSWORD_TOO_SHORT = 'Password must be at least 12 characters.';

    public const AUTH_REQUIRED = 'Authentication required.';

    public const GOOGLE_UNAVAILABLE = 'Google sign-in is not configured.';

    public const GOOGLE_FAILED = 'Unable to sign in with Google.';

    public const STORE_BUSY = 'The account is busy. Try again.';
}
