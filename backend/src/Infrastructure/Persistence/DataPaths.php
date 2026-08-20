<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use RuntimeException;

/**
 * Layout under DATA_DIR. Plaintext user sqlite lives in tmp/ so operators
 * can mount that directory as tmpfs in Docker.
 */
final class DataPaths
{
    private readonly string $dataDir;

    public function __construct(string $dataDir)
    {
        $trimmed = rtrim($dataDir, '/');
        if ($trimmed === '' || str_contains($trimmed, "\0")) {
            throw new RuntimeException('DATA_DIR is invalid.');
        }
        $this->dataDir = $trimmed;
    }

    public function root(): string
    {
        return $this->dataDir;
    }

    public function globalSqlite(): string
    {
        return $this->dataDir . '/global.sqlite';
    }

    public function usersDir(): string
    {
        return $this->dataDir . '/users';
    }

    public function tmpDir(): string
    {
        return $this->dataDir . '/tmp';
    }

    public function userEnc(string $userId): string
    {
        return $this->usersDir() . '/' . $userId . '.sqlite.enc';
    }

    public function userLock(string $userId): string
    {
        return $this->usersDir() . '/' . $userId . '.lock';
    }

    public function userEncStaging(string $userId): string
    {
        return $this->usersDir() . '/' . $userId . '.sqlite.enc.tmp';
    }

    public function ensure(): void
    {
        $this->ensureDir($this->dataDir);
        $this->ensureDir($this->usersDir());
        $this->ensureDir($this->tmpDir());
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create data directory.');
        }
    }
}
