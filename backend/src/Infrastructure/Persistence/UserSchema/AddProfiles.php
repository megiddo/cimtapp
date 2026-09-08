<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use PDO;

final class AddProfiles extends SqlFileUserSchemaStrategy
{
    public function __construct(
        string $migrationsDir,
        private readonly DefaultProfileBackfill $backfill,
    ) {
        parent::__construct($migrationsDir);
    }

    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V6Profiles;
    }

    protected function filename(): string
    {
        return '006_profiles.sql';
    }

    public function apply(PDO $pdo): void
    {
        parent::apply($pdo);
        $this->backfill->seed($pdo);
    }
}
