<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class ExclusiveUserLock
{
    private const LOCK_POLL_MICROS = 10_000;

    public function __construct(
        private readonly DataPaths $paths,
        private readonly int $lockTimeoutMs,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withLock(string $userId, callable $callback): mixed
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
}
