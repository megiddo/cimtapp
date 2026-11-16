<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class PersonRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listVisible(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, created_at, removed_at FROM people
             WHERE removed_at IS NULL
             ORDER BY created_at ASC, id ASC'
        );

        return $this->mapRows($stmt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRemoved(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, created_at, removed_at FROM people
             WHERE removed_at IS NOT NULL
             ORDER BY removed_at DESC, id ASC'
        );

        return $this->mapRows($stmt);
    }

    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, created_at, removed_at FROM people WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function nameTakenVisible(PDO $pdo, string $name, ?string $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM people WHERE removed_at IS NULL AND LOWER(name) = LOWER(:name)';
        $params = [':name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function insert(PDO $pdo, string $id, string $name, string $createdAt): void
    {
        $pdo->prepare(
            'INSERT INTO people (id, name, created_at, removed_at) VALUES (:id, :name, :at, NULL)'
        )->execute([':id' => $id, ':name' => $name, ':at' => $createdAt]);
    }

    public function updateName(PDO $pdo, string $id, string $name): void
    {
        $pdo->prepare('UPDATE people SET name = :name WHERE id = :id')->execute([':id' => $id, ':name' => $name]);
    }

    public function markRemoved(PDO $pdo, string $id, string $removedAt): void
    {
        $pdo->prepare('UPDATE people SET removed_at = :at WHERE id = :id')->execute([':id' => $id, ':at' => $removedAt]);
    }

    public function restore(PDO $pdo, string $id): void
    {
        $pdo->prepare('UPDATE people SET removed_at = NULL WHERE id = :id')->execute([':id' => $id]);
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
            'created_at' => (string) $row['created_at'],
            'removed_at' => $row['removed_at'] !== null ? (string) $row['removed_at'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapRows(\PDOStatement|false $stmt): array
    {
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }
}