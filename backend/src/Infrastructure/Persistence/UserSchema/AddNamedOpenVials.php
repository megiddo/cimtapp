<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

final class AddNamedOpenVials extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V4NamedOpenVials;
    }

    protected function filename(): string
    {
        return '004_named_open_vials.sql';
    }
}
