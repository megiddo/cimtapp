<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class GlobalMigrator
{
    public function __construct(
        private readonly DataPaths $paths,
        private readonly string $migrationsDir,
        private readonly SqliteFileMigrator $fileMigrator = new SqliteFileMigrator(),
    ) {
    }

    public function migrate(): int
    {
        $this->paths->ensure();

        return $this->fileMigrator->migrate($this->paths->globalSqlite(), $this->migrationsDir);
    }
}
