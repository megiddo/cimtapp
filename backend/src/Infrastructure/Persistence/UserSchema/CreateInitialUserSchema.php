<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

final class CreateInitialUserSchema extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V1Initial;
    }

    protected function filename(): string
    {
        return '001_create_schema.sql';
    }
}
