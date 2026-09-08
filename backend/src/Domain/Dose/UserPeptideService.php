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

    private readonly UserPeptideRepository $repository;
    private readonly PeptideSlugger $slugger;

    public function __construct(
        private readonly PeptideCatalog $catalog,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
        $this->repository = new UserPeptideRepository();
        $this->slugger = new PeptideSlugger($ids);
    }

    /**
     * Catalog first, then the user’s extra names.
     *
     * @return list<array{id: string, slug: string, name: string, sort_order: int}>
     */
    public function listAll(PDO $pdo): array
    {
        return array_values(array_merge($this->catalog->listActive(), $this->repository->listCustom($pdo)));
    }

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    public function require(PDO $pdo, string $id): array
    {
        $found = $this->repository->findCustom($pdo, $id) ?? $this->catalog->findActiveById($id);
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
        $slug = $this->slugger->uniqueSlug($this->listAll($pdo), $name);
        $this->repository->insert($pdo, [
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
}
