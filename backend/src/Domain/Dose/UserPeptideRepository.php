<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class UserPeptideRepository
{
    /**
     * @return list<array{id: string, slug: string, name: string, sort_order: int}>
     */
    public function listCustom(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, slug, name FROM user_peptide_types ORDER BY name ASC, id ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}|null
     */
    public function findCustom(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, slug, name FROM user_peptide_types WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO user_peptide_types (id, slug, name, created_at)
             VALUES (:id, :slug, :name, :created_at)'
        );
        $stmt->execute($values);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    public function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'sort_order' => UserPeptideService::CUSTOM_SORT_ORDER,
        ];
    }
}
