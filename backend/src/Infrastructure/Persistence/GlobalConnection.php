<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class GlobalConnection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly DataPaths $paths)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->paths->ensure();
            $this->pdo = new PDO('sqlite:' . $this->paths->globalSqlite(), null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $this->pdo;
    }
}
