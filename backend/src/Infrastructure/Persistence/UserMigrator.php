<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class UserMigrator
{
    public function __construct(
        private readonly string $migrationsDir,
        private readonly SqliteFileMigrator $fileMigrator = new SqliteFileMigrator(),
    ) {
    }

    public function migrate(string $sqlitePath): int
    {
        return $this->fileMigrator->migrate($sqlitePath, $this->migrationsDir);
    }
}
