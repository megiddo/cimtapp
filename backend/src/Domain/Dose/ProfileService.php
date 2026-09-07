<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class ProfileService
{
    public function __construct(
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, is_default, created_at
             FROM profiles
             ORDER BY is_default DESC, created_at ASC, id ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->find($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::PROFILE_UNKNOWN);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, is_default, created_at FROM profiles WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultProfile(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, is_default, created_at FROM profiles WHERE is_default = 1 LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainRecordNotFoundException(DoseConfig::PROFILE_UNKNOWN);
        }

        return $this->map($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function require(PDO $pdo, string $id): array
    {
        $found = $this->find($pdo, $id);
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
            if ($this->find($pdo, $id) === null) {
                throw new ValidationException(['profile_ids' => [DoseConfig::PROFILE_UNKNOWN]]);
            }
        }

        return $unique;
    }

    public function compoundLinked(PDO $pdo, string $compoundId, string $profileId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM compound_profiles
             WHERE compound_id = :compound_id AND profile_id = :profile_id
             LIMIT 1'
        );
        $stmt->execute([
            ':compound_id' => $compoundId,
            ':profile_id' => $profileId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<string>
     */
    public function profileIdsForCompound(PDO $pdo, string $compoundId): array
    {
        $stmt = $pdo->prepare(
            'SELECT profile_id FROM compound_profiles WHERE compound_id = :id ORDER BY profile_id ASC'
        );
        $stmt->execute([':id' => $compoundId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map(static fn (mixed $id): string => (string) $id, is_array($rows) ? $rows : []));
    }

    /**
     * @param list<string> $profileIds
     */
    public function replaceCompoundProfiles(PDO $pdo, string $compoundId, array $profileIds): void
    {
        $ids = $this->requireIds($pdo, $profileIds);
        $delete = $pdo->prepare('DELETE FROM compound_profiles WHERE compound_id = :id');
        $delete->execute([':id' => $compoundId]);
        $insert = $pdo->prepare(
            'INSERT INTO compound_profiles (compound_id, profile_id) VALUES (:compound_id, :profile_id)'
        );
        foreach ($ids as $profileId) {
            $insert->execute([
                ':compound_id' => $compoundId,
                ':profile_id' => $profileId,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        if (count($this->list($pdo)) >= DoseConfig::PROFILE_MAX) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_LIMIT]], DoseConfig::PROFILE_LIMIT);
        }

        $name = $this->requireName($fields);
        $id = $this->ids->uuid();
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (id, name, is_default, created_at)
             VALUES (:id, :name, 0, :created_at)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':created_at' => $this->timestamp(),
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $this->get($pdo, $id);
        $name = $this->requireName($fields);
        $stmt = $pdo->prepare('UPDATE profiles SET name = :name WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
        ]);

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $existing = $this->get($pdo, $id);
        if ((bool) $existing['is_default']) {
            throw new ValidationException(['id' => [DoseConfig::PROFILE_DEFAULT_REQUIRED]], DoseConfig::PROFILE_DEFAULT_REQUIRED);
        }
        if ($this->hasUses($pdo, $id)) {
            throw new ValidationException(['id' => [DoseConfig::PROFILE_HAS_USES]], DoseConfig::PROFILE_HAS_USES);
        }

        $unlink = $pdo->prepare('DELETE FROM compound_profiles WHERE profile_id = :id');
        $unlink->execute([':id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function hasUses(PDO $pdo, string $id): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM uses WHERE profile_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    private function requireName(FieldParser $fields): string
    {
        $name = $fields->requireString('name');
        if (strlen($name) > DoseConfig::PROFILE_NAME_MAX) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_NAME_TOO_LONG]]);
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'is_default' => (int) $row['is_default'] === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
