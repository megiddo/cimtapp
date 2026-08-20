<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use RuntimeException;

/**
 * Exclusive flock timed out. HTTP layer maps this to 503 in Phase 1.
 */
final class UserStoreLockedException extends RuntimeException
{
    public function __construct(string $message = 'User store is locked.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
