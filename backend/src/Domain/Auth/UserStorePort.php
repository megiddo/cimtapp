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

    /**
     * Decrypt the user store, apply pending schema mutations, and return
     * plaintext sqlite bytes. Temp files are shredded before returning.
     * Rewrites the .enc file only when a mutation was applied.
     */
    public function exportPlaintext(string $userId, string $dek): string;

    public function hasPreMigrationBackup(string $userId): bool;

    /**
     * Replace the live ciphertext with the pre-migration snapshot. The backup
     * file is kept so restore can run again. The next unlock remigrates.
     */
    public function restorePreMigrationBackup(string $userId): void;
}
