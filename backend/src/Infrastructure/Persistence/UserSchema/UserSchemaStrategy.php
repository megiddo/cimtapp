<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use PDO;

interface UserSchemaStrategy
{
    public function version(): UserStoreFormat;

    public function apply(PDO $pdo): void;
}
