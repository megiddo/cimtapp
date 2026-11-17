<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\PeptideSlugger;
use PDO;

final class LogPeptideService
{
    private readonly PeptideSlugger $slugger;

    public function __construct(
        private readonly LogPeptideRepository $repository,
        private readonly IdGenerator $ids,
    ) {
        $this->slugger = new PeptideSlugger($ids);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        return $this->repository->listVisible($pdo);
    }

    public function create(PDO $pdo, FieldParser $fields): array
    {
        $name = $fields->requireString('name');
        if (strlen($name) > 80) {
            throw new ValidationException(['name' => [DoseConfig::PEPTIDE_NAME_TOO_LONG]]);
        }
        if ($this->repository->nameTakenVisible($pdo, $name)) {
            throw new ValidationException(['name' => [LogBookConfig::PEPTIDE_NAME_TAKEN]]);
        }
        $existing = $this->repository->listVisible($pdo);
        $id = $this->ids->uuid();
        $slug = $this->slugger->uniqueSlug($existing, $name);
        $this->repository->insert($pdo, $id, $name, $slug);

        return $this->repository->findVisible($pdo, $id) ?? [];
    }

    public function remove(PDO $pdo, string $id): array
    {
        $row = $this->repository->findVisible($pdo, $id);
        if ($row === null) {
            throw new ValidationException(['id' => [LogBookConfig::PEPTIDE_UNKNOWN]]);
        }
        $this->repository->hide($pdo, $id);

        return $row;
    }

    public function requireVisible(PDO $pdo, string $id): array
    {
        $row = $this->repository->findVisible($pdo, $id);
        if ($row === null) {
            throw new ValidationException(['peptide_id' => [LogBookConfig::PEPTIDE_UNKNOWN]]);
        }

        return $row;
    }
}