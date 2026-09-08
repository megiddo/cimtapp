<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

/**
 * Legacy rule: PATCH may move a use onto another profile without a vial link check.
 */
final class MigrateUseProfile
{
    public function __construct(private readonly ProfileService $profiles)
    {
    }

    public function profileIdForPatch(PDO $pdo, FieldParser $fields, string $existingProfileId): string
    {
        if (!$fields->has('profile_id')) {
            return $existingProfileId;
        }

        return (string) $this->profiles->require($pdo, $fields->requireString('profile_id'))['id'];
    }
}
