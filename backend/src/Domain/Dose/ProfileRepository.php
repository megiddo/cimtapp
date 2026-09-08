<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class ProfileRepository
{
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
     * @return array<string, mixed>|null
     */
    public function defaultRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT id, name, is_default, created_at FROM profiles WHERE is_default = 1 LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (id, name, is_default, created_at)
             VALUES (:id, :name, 0, :created_at)'
        );
        $stmt->execute($values);
    }

    public function updateName(PDO $pdo, string $id, string $name): void
    {
        $stmt = $pdo->prepare('UPDATE profiles SET name = :name WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
        ]);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $unlink = $pdo->prepare('DELETE FROM compound_profiles WHERE profile_id = :id');
        $unlink->execute([':id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function hasUses(PDO $pdo, string $id): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM uses WHERE profile_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'is_default' => (int) $row['is_default'] === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
