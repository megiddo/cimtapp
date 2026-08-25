<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Persistence\UserSchema\UserSchemaCatalog;
use App\Infrastructure\Persistence\UserSchema\UserSchemaVersionDetector;
use PDO;
use RuntimeException;

/**
 * Applies {@see UserSchemaCatalog} strategies onto a user sqlite until it
 * matches {@see UserSchema\UserStoreFormat::current()}.
 */
final class UserMigrator
{
    private readonly UserSchemaCatalog $catalog;

    public function __construct(
        ?UserSchemaCatalog $catalog = null,
        private readonly UserSchemaVersionDetector $detector = new UserSchemaVersionDetector(),
    ) {
        $this->catalog = $catalog ?? UserSchemaCatalog::default();
    }

    public function migrate(string $sqlitePath): int
    {
        $parent = dirname($sqlitePath);
        if (!is_dir($parent) && !@mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Unable to create sqlite directory.');
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $applied = $this->migratePdo($pdo);
        $pdo = null;

        return $applied;
    }

    public function migratePdo(PDO $pdo): int
    {
        $current = $this->detector->detect($pdo);
        $applied = 0;
        foreach ($this->catalog->strategies() as $strategy) {
            $target = $strategy->version()->value;
            if ($target <= $current) {
                continue;
            }
            $strategy->apply($pdo);
            $this->detector->writeVersion($pdo, $target);
            $current = $target;
            $applied++;
        }

        return $applied;
    }
}
