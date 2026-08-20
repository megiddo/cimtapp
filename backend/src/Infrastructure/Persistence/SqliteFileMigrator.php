<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

/**
 * Applies versioned *.sql files onto a sqlite file. Second run is a no-op
 * for already-recorded versions.
 */
final class SqliteFileMigrator
{
    public function migrate(string $sqlitePath, string $migrationsDir): int
    {
        if (!is_dir($migrationsDir)) {
            throw new RuntimeException('Migrations directory is missing.');
        }

        $parent = dirname($sqlitePath);
        if (!is_dir($parent) && !@mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Unable to create sqlite directory.');
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $this->ensureMigrationsTable($pdo);

        $applied = 0;
        foreach ($this->sqlFiles($migrationsDir) as $file) {
            $version = basename($file);
            if ($this->isApplied($pdo, $version)) {
                continue;
            }

            if (!is_file($file)) {
                throw new RuntimeException('Unable to read migration file.');
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Unable to read migration file.');
            }

            $pdo->exec($sql);
            $stmt = $pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
            );
            $stmt->execute([
                ':version' => $version,
                ':applied_at' => gmdate('c'),
            ]);
            $applied++;
        }

        $pdo = null;

        return $applied;
    }

    /**
     * @return list<string>
     */
    private function sqlFiles(string $migrationsDir): array
    {
        $files = glob($migrationsDir . '/*.sql');
        if ($files === false) {
            throw new RuntimeException('Unable to list migration files.');
        }

        sort($files, SORT_STRING);

        return array_values($files);
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY NOT NULL,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function isApplied(PDO $pdo, string $version): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
        $stmt->execute([':version' => $version]);

        return $stmt->fetchColumn() !== false;
    }
}
