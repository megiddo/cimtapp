<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\DoseConfig;
use DateTimeZone;
use PDO;

/**
 * Facade for people CRUD and soft-remove rules.
 */
final class PersonService
{
    private readonly PersonRepository $repository;

    public function __construct(
        private readonly VialAssignmentService $vials,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
        $this->repository = new PersonRepository();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisible(PDO $pdo): array
    {
        return $this->repository->listVisible($pdo);
    }

  /**
   * @return list<array<string, mixed>>
   */
    public function listRemoved(PDO $pdo): array
    {
        return $this->repository->listRemoved($pdo);
    }

    public function create(PDO $pdo, FieldParser $fields): array
    {
        $name = $fields->requireString('name');
        if (strlen($name) > 80) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_NAME_TOO_LONG]]);
        }
        if ($this->repository->nameTakenVisible($pdo, $name)) {
            throw new ValidationException(['name' => [LogBookConfig::PERSON_NAME_TAKEN]]);
        }
        $id = $this->ids->uuid();
        $this->repository->insert($pdo, $id, $name, $this->timestamp());

        return $this->requireVisible($pdo, $id);
    }

    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $person = $this->requireVisible($pdo, $id);
        $name = $fields->requireString('name');
        if (strlen($name) > 80) {
            throw new ValidationException(['name' => [DoseConfig::PROFILE_NAME_TOO_LONG]]);
        }
        if ($this->repository->nameTakenVisible($pdo, $name, $id)) {
            throw new ValidationException(['name' => [LogBookConfig::PERSON_NAME_TAKEN]]);
        }
        $this->repository->updateName($pdo, $id, $name);
        $this->vials->refreshDisplayNamesForPerson($pdo, $id, $name);

        unset($person);

        return $this->requireVisible($pdo, $id);
    }

    public function remove(PDO $pdo, string $id): array
    {
        $this->requireVisible($pdo, $id);
        $this->repository->markRemoved($pdo, $id, $this->timestamp());
        $this->vials->unassignPerson($pdo, $id);

        return $this->repository->find($pdo, $id) ?? [];
    }

    public function restore(PDO $pdo, string $id): array
    {
        $person = $this->repository->find($pdo, $id);
        if ($person === null || $person['removed_at'] === null) {
            throw new ValidationException(['id' => [LogBookConfig::PERSON_UNKNOWN]]);
        }
        if ($this->repository->nameTakenVisible($pdo, $person['name'])) {
            throw new ValidationException(['name' => [LogBookConfig::PERSON_NAME_TAKEN]]);
        }
        $this->repository->restore($pdo, $id);

        return $this->requireVisible($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function requireVisible(PDO $pdo, string $id): array
    {
        $row = $this->repository->find($pdo, $id);
        if ($row === null || $row['removed_at'] !== null) {
            throw new ValidationException(['person_id' => [LogBookConfig::PERSON_UNKNOWN]]);
        }

        return $row;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}