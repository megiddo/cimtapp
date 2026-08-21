<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\UserStorePort;
use App\Domain\Crypto\Crypto;
use App\Domain\Crypto\CryptoException;
use PDO;

/**
 * Exclusive-lock, decrypt to DATA_DIR/tmp, run a callback, re-encrypt, unlock.
 */
final class UserStore implements UserStorePort
{
    private const USER_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const LOCK_POLL_MICROS = 10_000;

    public function __construct(
        private readonly Crypto $crypto,
        private readonly UserMigrator $userMigrator,
        private readonly DataPaths $paths,
        private readonly int $lockTimeoutMs = 5000,
    ) {
        if ($this->lockTimeoutMs < 1) {
            throw new UserStoreException('Lock timeout must be at least 1 ms.');
        }
    }

    public function create(string $userId, string $dek): void
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        $this->withLock($userId, function () use ($userId, $dek): void {
            $encPath = $this->paths->userEnc($userId);
            if (is_file($encPath)) {
                throw new UserStoreException('User store already exists.');
            }

            $plainPath = $this->uniquePlainPath($userId);
            $staging = $this->paths->userEncStaging($userId);
            try {
                $this->userMigrator->migrate($plainPath);
                $this->crypto->encryptFile($plainPath, $staging, $dek);
                if (!@rename($staging, $encPath)) {
                    throw new UserStoreException('Failed to persist user store.');
                }
            } finally {
                $this->shred($plainPath);
                $this->unlinkIfExists($staging);
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

        return $this->withLock($userId, function () use ($userId, $dek, $callback): mixed {
            $encPath = $this->paths->userEnc($userId);
            if (!is_file($encPath)) {
                throw new UserStoreException('User store not found.');
            }

            $plainPath = $this->uniquePlainPath($userId);
            $staging = $this->paths->userEncStaging($userId);

            try {
                $this->crypto->decryptFile($encPath, $plainPath, $dek);
            } catch (CryptoException $e) {
                $this->shred($plainPath);
                throw $e;
            }

            try {
                $this->userMigrator->migrate($plainPath);
                $pdo = $this->openPdo($plainPath);
                $result = $callback($pdo);
            } catch (\Throwable $e) {
                $pdo = null;
                $this->shred($plainPath);
                throw $e;
            }

            $pdo = null;
            try {
                $this->crypto->encryptFile($plainPath, $staging, $dek);
                if (!@rename($staging, $encPath)) {
                    throw new UserStoreException('Failed to persist user store.');
                }
            } finally {
                $this->shred($plainPath);
                $this->unlinkIfExists($staging);
            }

            return $result;
        });
    }

    /**
     * Decrypt the user store into memory and shred the tmp file before returning.
     * Does not rewrite the .enc file.
     */
    public function exportPlaintext(string $userId, string $dek): string
    {
        $this->assertUserId($userId);
        $this->paths->ensure();

        return $this->withLock($userId, function () use ($userId, $dek): string {
            $encPath = $this->paths->userEnc($userId);
            if (!is_file($encPath)) {
                throw new UserStoreException('User store not found.');
            }

            $plainPath = $this->uniquePlainPath($userId);
            try {
                $this->crypto->decryptFile($encPath, $plainPath, $dek);
                $bytes = file_get_contents($plainPath);
                if (!is_string($bytes) || $bytes === '') {
                    throw new UserStoreException('Unable to export user store.');
                }

                return $bytes;
            } finally {
                $this->shred($plainPath);
            }
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withLock(string $userId, callable $callback): mixed
    {
        $lockPath = $this->paths->userLock($userId);
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new UserStoreException('Unable to open user store lock.');
        }

        $deadline = microtime(true) + ($this->lockTimeoutMs / 1000);
        $locked = false;
        try {
            while (!($locked = flock($handle, LOCK_EX | LOCK_NB))) {
                if (microtime(true) >= $deadline) {
                    throw new UserStoreLockedException();
                }
                usleep(self::LOCK_POLL_MICROS);
            }

            return $callback();
        } finally {
            if ($locked) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    private function openPdo(string $plainPath): PDO
    {
        $pdo = new PDO('sqlite:' . $plainPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    private function uniquePlainPath(string $userId): string
    {
        return $this->paths->tmpDir() . '/' . $userId . '-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    private function assertUserId(string $userId): void
    {
        if (preg_match(self::USER_ID_PATTERN, $userId) !== 1) {
            throw new UserStoreException('User id must be a UUID.');
        }
    }

    private function shred(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $size = filesize($path);
        if (is_int($size) && $size > 0) {
            $handle = fopen($path, 'r+b');
            if ($handle !== false) {
                fwrite($handle, str_repeat("\0", $size));
                fclose($handle);
            }
        }

        unlink($path);
    }

    private function unlinkIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
