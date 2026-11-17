<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class LogPeptideRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listVisible(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, slug, removed_at FROM peptides WHERE removed_at IS NULL ORDER BY name ASC, id ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name, slug, removed_at FROM peptides WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function findVisible(PDO $pdo, string $id): ?array
    {
        $row = $this->find($pdo, $id);
        if ($row === null || $row['removed_at'] !== null) {
            return null;
        }

        return $row;
    }

    public function nameTakenVisible(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM peptides WHERE removed_at IS NULL AND LOWER(name) = LOWER(:name) LIMIT 1'
        );
        $stmt->execute([':name' => $name]);

        return $stmt->fetchColumn() !== false;
    }

    public function insert(PDO $pdo, string $id, string $name, string $slug): void
    {
        $pdo->prepare(
            'INSERT INTO peptides (id, name, slug, removed_at) VALUES (:id, :name, :slug, NULL)'
        )->execute([':id' => $id, ':name' => $name, ':slug' => $slug]);
    }

    public function hide(PDO $pdo, string $id): void
    {
        $pdo->prepare(
            'UPDATE peptides SET removed_at = :at WHERE id = :id AND removed_at IS NULL'
        )->execute([':id' => $id, ':at' => gmdate('Y-m-d\TH:i:s\Z')]);
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
            'slug' => (string) $row['slug'],
            'removed_at' => $row['removed_at'] !== null ? (string) $row['removed_at'] : null,
        ];
    }
}