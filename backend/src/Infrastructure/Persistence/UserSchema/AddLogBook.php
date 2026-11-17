<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use PDO;
use Psr\Log\LoggerInterface;

final class AddLogBook extends SqlFileUserSchemaStrategy
{
    public function __construct(
        string $migrationsDir,
        private readonly LogBookMigrationBackfill $backfill,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($migrationsDir);
    }

    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V8LogBook;
    }

    protected function filename(): string
    {
        return '008_log_book.sql';
    }

    public function apply(PDO $pdo): void
    {
        parent::apply($pdo);
        $this->backfill->apply($pdo);
        $this->logger->info('logbook.migration.applied');
    }
}