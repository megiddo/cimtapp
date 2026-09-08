<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class ProfilePolicy
{
    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->profiles->find($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::PROFILE_UNKNOWN);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultProfile(PDO $pdo): array
    {
        $row = $this->profiles->defaultRow($pdo);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::PROFILE_UNKNOWN);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function require(PDO $pdo, string $id): array
    {
        $found = $this->profiles->find($pdo, $id);
        if ($found === null) {
            throw new ValidationException(['profile_id' => [DoseConfig::PROFILE_UNKNOWN]]);
        }

        return $found;
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    public function requireIds(PDO $pdo, array $ids): array
    {
        if ($ids === []) {
            throw new ValidationException(['profile_ids' => [DoseConfig::PROFILE_REQUIRED]]);
        }

        $unique = array_values(array_unique($ids));
        foreach ($unique as $id) {
            if ($this->profiles->find($pdo, $id) === null) {
                throw new ValidationException(['profile_ids' => [DoseConfig::PROFILE_UNKNOWN]]);
            }
        }

        return $unique;
    }

    public function assertCanCreate(PDO $pdo): void
    {
        if (count($this->profiles->list($pdo)) >= DoseConfig::PROFILE_MAX) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_LIMIT]], DoseConfig::PROFILE_LIMIT);
        }
    }

    public function assertCanDelete(PDO $pdo, array $existing): void
    {
        if ((bool) $existing['is_default']) {
            throw new ValidationException(['id' => [DoseConfig::PROFILE_DEFAULT_REQUIRED]], DoseConfig::PROFILE_DEFAULT_REQUIRED);
        }
        if ($this->profiles->hasUses($pdo, (string) $existing['id'])) {
            throw new ValidationException(['id' => [DoseConfig::PROFILE_HAS_USES]], DoseConfig::PROFILE_HAS_USES);
        }
    }

    public function requireName(FieldParser $fields): string
    {
        $name = $fields->requireString('name');
        if (strlen($name) > DoseConfig::PROFILE_NAME_MAX) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_NAME_TOO_LONG]]);
        }

        return $name;
    }
}
