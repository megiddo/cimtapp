<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

final class AddBacAndSyringeStock extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V2BacAndSyringeStock;
    }

    protected function filename(): string
    {
        return '002_bac_and_syringe_stock.sql';
    }
}
