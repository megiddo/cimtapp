<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class LidColorRepository
{
    public function findById(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name, created_at FROM lid_colors WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function findByNameCi(PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name, created_at FROM lid_colors WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function insert(PDO $pdo, string $id, string $name, string $createdAt): void
    {
        $pdo->prepare(
            'INSERT INTO lid_colors (id, name, created_at) VALUES (:id, :name, :at)'
        )->execute([':id' => $id, ':name' => $name, ':at' => $createdAt]);
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
            'created_at' => (string) $row['created_at'],
        ];
    }
}