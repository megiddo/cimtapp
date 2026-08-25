<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

/**
 * Integer format versions for per-user sqlite files. Strategies mutate a store
 * from the prior version to the one they declare.
 */
enum UserStoreFormat: int
{
    case V1Initial = 1;
    case V2BacAndSyringeStock = 2;
    case V3UserPeptideTypes = 3;
    case V4NamedOpenVials = 4;

    public static function current(): self
    {
        return self::V4NamedOpenVials;
    }
}
