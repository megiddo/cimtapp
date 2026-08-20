<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use PDO;

/**
 * Opens an encrypted per-user sqlite for the duration of a callback.
 */
interface UserStorePort
{
    public function create(string $userId, string $dek): void;

    /**
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public function withUnlocked(string $userId, string $dek, callable $callback): mixed;
}
