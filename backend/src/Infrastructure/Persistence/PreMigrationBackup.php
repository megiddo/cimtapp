<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class PreMigrationBackup
{
    public function __construct(private readonly DataPaths $paths)
    {
    }

    public function exists(string $userId): bool
    {
        return is_file($this->paths->userEncBackup($userId));
    }

    public function snapshotEncIfNeeded(string $encPath, string $userId, int $applied): void
    {
        if ($applied < 1 || !is_file($encPath)) {
            return;
        }

        $bakPath = $this->paths->userEncBackup($userId);
        if (is_file($bakPath)) {
            return;
        }

        $staging = $this->paths->userEncBackupStaging($userId);
        $this->copyAtomic($encPath, $staging, $bakPath, 'Failed to snapshot user store.');
    }

    public function restore(string $userId): void
    {
        $bakPath = $this->paths->userEncBackup($userId);
        if (!is_file($bakPath)) {
            throw new UserStoreException('Pre-migration backup not found.');
        }

        $encPath = $this->paths->userEnc($userId);
        $staging = $this->paths->userEncStaging($userId);
        $this->copyAtomic($bakPath, $staging, $encPath, 'Failed to restore user store.');
    }

    public function copyAtomic(string $from, string $staging, string $to, string $failureMessage): void
    {
        if (!@copy($from, $staging) || !@rename($staging, $to)) {
            $this->unlinkIfExists($staging);
            throw new UserStoreException($failureMessage);
        }
    }

    public function unlinkIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
