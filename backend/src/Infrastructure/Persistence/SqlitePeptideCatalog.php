<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Dose\PeptideCatalog;
use PDO;

final class SqlitePeptideCatalog implements PeptideCatalog
{
    public function __construct(private readonly GlobalConnection $global)
    {
    }

    public function listActive(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT slug, name, sort_order
             FROM peptide_types
             WHERE is_active = 1
             ORDER BY sort_order ASC, slug ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->mapRow(...), $rows));
    }

    public function findActiveById(string $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT slug, name, sort_order
             FROM peptide_types
             WHERE slug = :slug AND is_active = 1'
        );
        $stmt->execute([':slug' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    private function mapRow(array $row): array
    {
        $slug = (string) $row['slug'];

        return [
            'id' => $slug,
            'slug' => $slug,
            'name' => (string) $row['name'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    private function pdo(): PDO
    {
        return $this->global->pdo();
    }
}
