<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use PDO;

/**
 * Reads the format version of a user sqlite, including stores that predate
 * `user_store_format` (legacy `schema_migrations` filenames or table shape).
 */
final class UserSchemaVersionDetector
{
    public const TABLE = 'user_store_format';

    /** @var array<string, int> */
    private const LEGACY_FILES = [
        '001_create_schema.sql' => 1,
        '002_bac_and_syringe_stock.sql' => 2,
        '003_user_peptide_types.sql' => 3,
        '004_named_open_vials.sql' => 4,
        '005_archive_and_adjustments.sql' => 5,
    ];

    public function detect(PDO $pdo): int
    {
        $stored = $this->storedVersion($pdo);
        if ($stored !== null) {
            return $stored;
        }

        $fromMigrations = $this->fromSchemaMigrations($pdo);
        if ($fromMigrations > 0) {
            return $fromMigrations;
        }

        return $this->fromSchemaShape($pdo);
    }

    public function writeVersion(PDO $pdo, int $version): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_store_format (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                version INTEGER NOT NULL
            )'
        );
        $stmt = $pdo->prepare(
            'INSERT INTO user_store_format (id, version) VALUES (1, :version)
             ON CONFLICT(id) DO UPDATE SET version = excluded.version'
        );
        $stmt->execute([':version' => $version]);
    }

    private function storedVersion(PDO $pdo): ?int
    {
        if (!$this->tableExists($pdo, self::TABLE)) {
            return null;
        }

        $stmt = $pdo->query('SELECT version FROM user_store_format WHERE id = 1');
        $value = $stmt === false ? false : $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        return (int) $value;
    }

    private function fromSchemaMigrations(PDO $pdo): int
    {
        if (!$this->tableExists($pdo, 'schema_migrations')) {
            return 0;
        }

        $stmt = $pdo->query('SELECT version FROM schema_migrations');
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_COLUMN);
        $max = 0;
        foreach ($rows as $filename) {
            $mapped = self::LEGACY_FILES[(string) $filename] ?? 0;
            if ($mapped > $max) {
                $max = $mapped;
            }
        }

        return $max;
    }

    private function fromSchemaShape(PDO $pdo): int
    {
        if ($this->tableExists($pdo, 'compound_adjustments') || $this->hasColumn($pdo, 'compounds', 'archived_at')) {
            return UserStoreFormat::V5ArchiveAndAdjustments->value;
        }
        if ($this->hasColumn($pdo, 'compounds', 'name')) {
            return UserStoreFormat::V4NamedOpenVials->value;
        }
        if ($this->tableExists($pdo, 'user_peptide_types')) {
            return UserStoreFormat::V3UserPeptideTypes->value;
        }
        if ($this->tableExists($pdo, 'bac_bottles') || $this->hasColumn($pdo, 'syringe_profiles', 'quantity')) {
            return UserStoreFormat::V2BacAndSyringeStock->value;
        }
        if ($this->tableExists($pdo, 'compounds') || $this->tableExists($pdo, 'account')) {
            return UserStoreFormat::V1Initial->value;
        }

        return 0;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1"
        );
        $stmt->execute([':name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        if (!$this->tableExists($pdo, $table)) {
            return false;
        }

        $stmt = $pdo->query('PRAGMA table_info(' . $this->quoteIdent($table) . ')');
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if ((string) ($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
