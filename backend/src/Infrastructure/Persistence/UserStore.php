<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\UserStorePort;
use App\Domain\Crypto\Crypto;
use PDO;

/**
 * Exclusive-lock, decrypt to DATA_DIR/tmp, run a callback, re-encrypt, unlock.
 * The first schema mutation copies `{uuid}.sqlite.enc` to `{uuid}.sqlite.enc.bak`.
 */
final class UserStore implements UserStorePort
{
    private const USER_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private readonly ExclusiveUserLock $lock;
    private readonly UserStoreCipherSession $cipher;
    private readonly PreMigrationBackup $backup;

    public function __construct(
        private readonly Crypto $crypto,
        private readonly UserMigrator $userMigrator,
        private readonly DataPaths $paths,
        private readonly int $lockTimeoutMs = 5000,
    ) {
        if ($this->lockTimeoutMs < 1) {
            throw new UserStoreException('Lock timeout must be at least 1 ms.');
        }
        $this->backup = new PreMigrationBackup($this->paths);
        $this->lock = new ExclusiveUserLock($this->paths, $this->lockTimeoutMs);
        $this->cipher = new UserStoreCipherSession($this->crypto, $this->paths, $this->backup);
    }

    public function create(string $userId, string $dek): void
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        $this->lock->withLock($userId, function () use ($userId, $dek): void {
            $encPath = $this->paths->userEnc($userId);
            if (is_file($encPath)) {
                throw new UserStoreException('User store already exists.');
            }

            $plainPath = $this->cipher->uniquePlainPath($userId);
            $staging = $this->paths->userEncStaging($userId);
            try {
                $this->userMigrator->migrate($plainPath);
                $this->cipher->encryptReplace($plainPath, $staging, $encPath, $dek);
            } finally {
                $this->cipher->cleanup($plainPath, $staging);
            }
        });
    }

    /**
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public function withUnlocked(string $userId, string $dek, callable $callback): mixed
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        return $this->lock->withLock($userId, function () use ($userId, $dek, $callback): mixed {
            $encPath = $this->paths->userEnc($userId);
            if (!is_file($encPath)) {
                throw new UserStoreException('User store not found.');
            }

            $plainPath = $this->cipher->uniquePlainPath($userId);
            $staging = $this->paths->userEncStaging($userId);

            $this->cipher->decryptToPlain($encPath, $plainPath, $dek);

            try {
                $applied = $this->userMigrator->migrate($plainPath);
                $this->backup->snapshotEncIfNeeded($encPath, $userId, $applied);
                $pdo = $this->cipher->openPdo($plainPath);
                $result = $callback($pdo);
            } catch (\Throwable $e) {
                $pdo = null;
                $this->cipher->shred($plainPath);
                throw $e;
            }

            $pdo = null;
            try {
                $this->cipher->encryptReplace($plainPath, $staging, $encPath, $dek);
            } finally {
                $this->cipher->cleanup($plainPath, $staging);
            }

            return $result;
        });
    }

    /**
     * Decrypt the user store, apply pending schema mutations, and return
     * plaintext sqlite bytes. Temp files are shredded before returning.
     * Rewrites the .enc file only when a mutation was applied.
     */
    public function exportPlaintext(string $userId, string $dek): string
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        return $this->lock->withLock($userId, function () use ($userId, $dek): string {
            $encPath = $this->paths->userEnc($userId);
            if (!is_file($encPath)) {
                throw new UserStoreException('User store not found.');
            }

            $plainPath = $this->cipher->uniquePlainPath($userId);
            $staging = $this->paths->userEncStaging($userId);
            try {
                $this->cipher->decryptToPlain($encPath, $plainPath, $dek);
                $applied = $this->userMigrator->migrate($plainPath);
                $this->backup->snapshotEncIfNeeded($encPath, $userId, $applied);
                $bytes = file_get_contents($plainPath);
                if (!is_string($bytes) || $bytes === '') {
                    throw new UserStoreException('Unable to export user store.');
                }
                if ($applied > 0) {
                    $this->cipher->encryptReplace($plainPath, $staging, $encPath, $dek);
                }

                return $bytes;
            } finally {
                $this->cipher->cleanup($plainPath, $staging);
            }
        });
    }

    public function hasPreMigrationBackup(string $userId): bool
    {
        $this->assertUserId($userId);

        return $this->backup->exists($userId);
    }

    /**
     * Replace the live ciphertext with the pre-migration snapshot. The backup
     * file is kept so restore can run again. The next unlock remigrates.
     */
    public function restorePreMigrationBackup(string $userId): void
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        $this->lock->withLock($userId, function () use ($userId): void {
            $this->backup->restore($userId);
        });
    }

    private function assertUserId(string $userId): void
    {
        if (preg_match(self::USER_ID_PATTERN, $userId) !== 1) {
            throw new UserStoreException('User id must be a UUID.');
        }
    }
}
