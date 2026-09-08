<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Crypto\Crypto;
use App\Domain\Crypto\CryptoException;
use PDO;

final class UserStoreCipherSession
{
    public function __construct(
        private readonly Crypto $crypto,
        private readonly DataPaths $paths,
        private readonly PreMigrationBackup $backup,
    ) {
    }

    public function uniquePlainPath(string $userId): string
    {
        return $this->paths->tmpDir() . '/' . $userId . '-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    public function openPdo(string $plainPath): PDO
    {
        $pdo = new PDO('sqlite:' . $plainPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    public function decryptToPlain(string $encPath, string $plainPath, string $dek): void
    {
        try {
            $this->crypto->decryptFile($encPath, $plainPath, $dek);
        } catch (CryptoException $e) {
            $this->shred($plainPath);
            throw $e;
        }
    }

    public function encryptReplace(string $plainPath, string $staging, string $encPath, string $dek): void
    {
        $this->crypto->encryptFile($plainPath, $staging, $dek);
        if (!@rename($staging, $encPath)) {
            throw new UserStoreException('Failed to persist user store.');
        }
    }

    public function shred(string $path): void
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

    public function cleanup(string $plainPath, string $staging): void
    {
        $this->shred($plainPath);
        $this->backup->unlinkIfExists($staging);
    }
}
