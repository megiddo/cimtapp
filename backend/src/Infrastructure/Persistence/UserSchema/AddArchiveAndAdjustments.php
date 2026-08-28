<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

final class AddArchiveAndAdjustments extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V5ArchiveAndAdjustments;
    }

    protected function filename(): string
    {
        return '005_archive_and_adjustments.sql';
    }
}
