<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use DateTimeZone;
use PDO;

final class UserPeptideService
{
    public const CUSTOM_SORT_ORDER = 1000;

    public function __construct(
        private readonly PeptideCatalog $catalog,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Catalog first, then the user’s extra names.
     *
     * @return list<array{id: string, slug: string, name: string, sort_order: int}>
     */
    public function listAll(PDO $pdo): array
    {
        return array_values(array_merge($this->catalog->listActive(), $this->listCustom($pdo)));
    }

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    public function require(PDO $pdo, string $id): array
    {
        $found = $this->findCustom($pdo, $id) ?? $this->catalog->findActiveById($id);
        if ($found === null) {
            throw new ValidationException(['peptide_type_id' => [DoseConfig::PEPTIDE_UNKNOWN]]);
        }

        return $found;
    }

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $name = $fields->requireString('name');
        if (strlen($name) > 80) {
            throw new ValidationException(['name' => [DoseConfig::PEPTIDE_NAME_TOO_LONG]]);
        }
        if ($this->nameTaken($pdo, $name)) {
            throw new ValidationException(['name' => [DoseConfig::PEPTIDE_NAME_TAKEN]]);
        }

        $id = $this->ids->uuid();
        $slug = $this->uniqueSlug($pdo, $name);
        $stmt = $pdo->prepare(
            'INSERT INTO user_peptide_types (id, slug, name, created_at)
             VALUES (:id, :slug, :name, :created_at)'
        );
        $stmt->execute([
            ':id' => $id,
            ':slug' => $slug,
            ':name' => $name,
            ':created_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ]);

        return [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => self::CUSTOM_SORT_ORDER,
        ];
    }

    /**
     * @return list<array{id: string, slug: string, name: string, sort_order: int}>
     */
    private function listCustom(PDO $pdo): array
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
    private function findCustom(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, slug, name FROM user_peptide_types WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    private function nameTaken(PDO $pdo, string $name): bool
    {
        $needle = strtolower($name);
        foreach ($this->listAll($pdo) as $row) {
            if (strtolower((string) $row['name']) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function uniqueSlug(PDO $pdo, string $name): string
    {
        $base = $this->slug($name);
        $taken = [];
        foreach ($this->listAll($pdo) as $row) {
            $taken[(string) $row['slug']] = true;
            $taken[(string) $row['id']] = true;
        }
        if (!isset($taken[$base])) {
            return $base;
        }

        return $base . '-' . substr($this->ids->uuid(), 0, 8);
    }

    private function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'peptide' : $slug;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    private function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'sort_order' => self::CUSTOM_SORT_ORDER,
        ];
    }
}
