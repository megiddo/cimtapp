<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class CompoundQueryService
{
    public function __construct(
        private readonly CompoundRepository $compounds,
        private readonly CompoundPresenter $presenter,
        private readonly SyringeService $syringes,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, ?StockList $query = null): array
    {
        $query ??= StockList::openOnly();
        $default = $this->syringes->defaultSyringe($pdo);

        return array_values(array_map(
            fn (array $row): array => $this->presenter->present($pdo, $row, $default),
            $this->compounds->listRows($pdo, $query),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function current(PDO $pdo): array
    {
        $row = $this->compounds->currentRow($pdo);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }

        return $this->presenter->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOpen(PDO $pdo): array
    {
        return $this->list($pdo, StockList::openOnly());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentRemainder(PDO $pdo): ?array
    {
        $row = $this->compounds->currentRow($pdo);
        if ($row === null) {
            return null;
        }

        return $this->presenter->remainderSummary(
            $this->presenter->present($pdo, $row, $this->syringes->defaultSyringe($pdo))
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openRemainders(PDO $pdo): array
    {
        return array_map(
            $this->presenter->remainderSummary(...),
            $this->listOpen($pdo),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->compounds->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }

        return $this->presenter->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        $row = $this->compounds->findRow($pdo, $id);

        return $row === null ? null : $this->presenter->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
    }
}
