<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class BacBottleService
{
    private readonly BacBottleRepository $repository;
    private readonly BacLedger $ledger;
    private readonly BacBottleLifecycle $lifecycle;

    public function __construct(
        private readonly DoseCalculator $doses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        ArchivePolicy $archivePolicy,
    ) {
        $this->repository = new BacBottleRepository();
        $this->ledger = new BacLedger($this->repository, $doses);
        $this->lifecycle = new BacBottleLifecycle($this->repository, $this->ledger, $archivePolicy, $clock);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, ?StockList $query = null): array
    {
        $query ??= StockList::openOnly();
        $currentId = $this->repository->currentId($pdo);

        return array_values(array_map(
            fn (array $row): array => $this->repository->map($row, $currentId),
            $this->repository->listRows($pdo, $query),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function current(PDO $pdo): array
    {
        $row = $this->repository->currentRow($pdo);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        return $this->repository->map($row, (string) $row['id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->lifecycle->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $volumeMl = $this->doses->roundVolume($fields->requirePositiveFloat('volume_ml'));
        $openedAt = $fields->optionalDatetime('opened_at') ?? $this->timestamp();
        $notes = $fields->optionalString('notes');
        $id = $this->ids->uuid();

        $this->repository->insert($pdo, [
            ':id' => $id,
            ':volume_ml' => $volumeMl,
            ':remaining_ml' => $volumeMl,
            ':opened_at' => $openedAt,
            ':notes' => $notes,
            ':created_at' => $this->timestamp(),
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $openedAt = $fields->has('opened_at')
            ? $fields->requireDatetime('opened_at')
            : (string) $existing['opened_at'];
        $notes = $fields->has('notes') ? $fields->optionalString('notes') : $existing['notes'];

        $this->repository->updateNotes($pdo, [
            ':id' => $id,
            ':opened_at' => $openedAt,
            ':notes' => $notes,
        ]);

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $existing = $this->get($pdo, $id);
        if (abs((float) $existing['remaining_ml'] - (float) $existing['volume_ml']) > 1e-9) {
            throw new ValidationException(['id' => [DoseConfig::BAC_IN_USE]], DoseConfig::BAC_IN_USE);
        }

        $this->repository->delete($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->lifecycle->burn($pdo, $id, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(PDO $pdo, string $id): array
    {
        return $this->lifecycle->archive($pdo, $id);
    }

    public function debitCurrent(PDO $pdo, float $ml): ?string
    {
        return $this->ledger->debitCurrent($pdo, $ml);
    }

    public function applyMixDelta(PDO $pdo, ?string $bottleId, float $oldMl, float $newMl): ?string
    {
        return $this->ledger->applyMixDelta($pdo, $bottleId, $oldMl, $newMl);
    }

    public function credit(PDO $pdo, ?string $bottleId, float $ml): void
    {
        $this->ledger->credit($pdo, $bottleId, $ml);
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
