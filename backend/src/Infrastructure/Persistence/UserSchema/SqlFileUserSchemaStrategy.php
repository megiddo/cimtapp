<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use PDO;
use RuntimeException;

abstract class SqlFileUserSchemaStrategy implements UserSchemaStrategy
{
    public function __construct(protected readonly string $migrationsDir)
    {
    }

    abstract protected function filename(): string;

    public function apply(PDO $pdo): void
    {
        $path = $this->migrationsDir . '/' . $this->filename();
        if (!is_file($path)) {
            throw new RuntimeException('Unable to read migration file.');
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration file.');
        }

        $pdo->exec($sql);
    }
}
