<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use PDO;

final class UseService
{
    private readonly UseQueryService $queries;
    private readonly UseWriteService $writes;

    public function __construct(
        DoseCalculator $doses,
        CompoundService $compounds,
        SyringeService $syringes,
        ProfileService $profiles,
        IdGenerator $ids,
        Clock $clock,
    ) {
        $repository = new UseRepository();
        $this->queries = new UseQueryService($repository);
        $this->writes = new UseWriteService(
            $repository,
            $doses,
            $compounds,
            $syringes,
            $profiles,
            new MigrateUseProfile($profiles),
            $ids,
            $clock,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, FieldParser $query): array
    {
        return $this->queries->list($pdo, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->queries->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        return $this->writes->create($pdo, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->writes->patch($pdo, $id, $fields);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->writes->delete($pdo, $id);
    }
}
