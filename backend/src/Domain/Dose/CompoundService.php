<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use PDO;

final class CompoundService
{
    private readonly CompoundRepository $repository;
    private readonly CompoundQueryService $queries;
    private readonly CompoundMixService $mix;
    private readonly CompoundAdjustmentService $adjustments;

    public function __construct(
        DoseCalculator $doses,
        UserPeptideService $peptides,
        SyringeService $syringes,
        BacBottleService $bacBottles,
        ProfileService $profiles,
        IdGenerator $ids,
        private readonly Clock $clock,
        private readonly ArchivePolicy $archivePolicy,
    ) {
        $this->repository = new CompoundRepository($doses);
        $presenter = new CompoundPresenter($this->repository, $doses, $profiles);
        $this->queries = new CompoundQueryService($this->repository, $presenter, $syringes);
        $this->adjustments = new CompoundAdjustmentService(
            $this->repository,
            $this->queries,
            $doses,
            $ids,
            $clock,
        );
        $this->mix = new CompoundMixService(
            $this->repository,
            $this->queries,
            $this->adjustments,
            $doses,
            $peptides,
            $bacBottles,
            $profiles,
            $ids,
            $clock,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, ?StockList $query = null): array
    {
        return $this->queries->list($pdo, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function current(PDO $pdo): array
    {
        return $this->queries->current($pdo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOpen(PDO $pdo): array
    {
        return $this->queries->listOpen($pdo);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentRemainder(PDO $pdo): ?array
    {
        return $this->queries->currentRemainder($pdo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openRemainders(PDO $pdo): array
    {
        return $this->queries->openRemainders($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->queries->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        return $this->queries->find($pdo, $id);
    }

    public function usedPeptideMg(PDO $pdo, string $compoundId, ?string $excludeUseId = null): float
    {
        return $this->repository->usedPeptideMg($pdo, $compoundId, $excludeUseId);
    }

    public function adjustmentPeptideMg(PDO $pdo, string $compoundId): float
    {
        return $this->repository->adjustmentPeptideMg($pdo, $compoundId);
    }

    public function hasUses(PDO $pdo, string $compoundId): bool
    {
        return $this->repository->hasUses($pdo, $compoundId);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        return $this->mix->create($pdo, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->mix->patch($pdo, $id, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function adjust(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->adjustments->adjust($pdo, $id, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(PDO $pdo, string $id): array
    {
        $existing = $this->queries->get($pdo, $id);
        $this->archivePolicy->apply(new CompoundStock($pdo, $existing), $this->clock);

        return $this->queries->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->mix->delete($pdo, $id);
    }
}
