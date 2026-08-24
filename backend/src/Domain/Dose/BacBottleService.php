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
    public function __construct(
        private readonly DoseCalculator $doses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        $currentId = $this->currentId($pdo);
        $stmt = $pdo->query(
            'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at
             FROM bac_bottles
             ORDER BY opened_at DESC, id DESC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map(
            fn (array $row): array => $this->map($row, $currentId),
            is_array($rows) ? $rows : [],
        ));
    }

    /**
     * Latest opened bottle that still has remaining water. 404 when none.
     *
     * @return array<string, mixed>
     */
    public function current(PDO $pdo): array
    {
        $row = $this->currentRow($pdo);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        return $this->map($row, (string) $row['id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        return $this->map($row, $this->currentId($pdo));
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

        $stmt = $pdo->prepare(
            'INSERT INTO bac_bottles (id, volume_ml, remaining_ml, opened_at, notes, created_at)
             VALUES (:id, :volume_ml, :remaining_ml, :opened_at, :notes, :created_at)'
        );
        $stmt->execute([
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

        $stmt = $pdo->prepare(
            'UPDATE bac_bottles SET opened_at = :opened_at, notes = :notes WHERE id = :id'
        );
        $stmt->execute([
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

        $stmt = $pdo->prepare('DELETE FROM bac_bottles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        $row = $this->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        $this->debitRow($pdo, $row, $fields->requirePositiveFloat('ml'), 'ml');

        return $this->get($pdo, $id);
    }

    public function debitCurrent(PDO $pdo, float $ml): ?string
    {
        $row = $this->currentRow($pdo);
        if ($row === null) {
            return null;
        }

        return $this->debitRow($pdo, $row, $ml);
    }

    public function applyMixDelta(PDO $pdo, ?string $bottleId, float $oldMl, float $newMl): ?string
    {
        $delta = $this->doses->roundVolume($newMl - $oldMl);
        if (abs($delta) < 1e-9) {
            return $bottleId;
        }

        if ($delta > 0.0) {
            if ($bottleId === null || $bottleId === '') {
                return $this->debitCurrent($pdo, $delta);
            }

            $row = $this->findRow($pdo, $bottleId);
            if ($row === null) {
                return $this->debitCurrent($pdo, $delta);
            }
            $this->debitRow($pdo, $row, $delta);

            return $bottleId;
        }

        if ($bottleId !== null && $bottleId !== '') {
            $this->credit($pdo, $bottleId, -$delta);
        }

        return $bottleId;
    }

    public function credit(PDO $pdo, ?string $bottleId, float $ml): void
    {
        if ($bottleId === null || $bottleId === '') {
            return;
        }
        $row = $this->findRow($pdo, $bottleId);
        if ($row === null) {
            return;
        }

        $volume = (float) $row['volume_ml'];
        $remaining = $this->doses->roundVolume((float) $row['remaining_ml'] + $ml);
        if ($remaining > $volume) {
            $remaining = $volume;
        }
        $this->setRemaining($pdo, $bottleId, $remaining);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function debitRow(PDO $pdo, array $row, float $ml, string $field = 'bac_water_ml'): string
    {
        $ml = $this->doses->roundVolume($ml);
        $remaining = (float) $row['remaining_ml'];
        if ($ml - $remaining > 1e-9) {
            $message = DoseConfig::bacOverdraw(
                DoseConfig::trimNumber($ml),
                DoseConfig::trimNumber($this->doses->roundVolume($remaining)),
            );
            throw new ValidationException([$field => [$message]], $message);
        }

        $this->setRemaining($pdo, (string) $row['id'], $this->doses->roundVolume($remaining - $ml));

        return (string) $row['id'];
    }

    private function setRemaining(PDO $pdo, string $id, float $remainingMl): void
    {
        $stmt = $pdo->prepare('UPDATE bac_bottles SET remaining_ml = :remaining_ml WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':remaining_ml' => $remainingMl,
        ]);
    }

    private function currentId(PDO $pdo): ?string
    {
        $row = $this->currentRow($pdo);

        return $row === null ? null : (string) $row['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at
             FROM bac_bottles
             WHERE remaining_ml > 0
             ORDER BY opened_at DESC, id DESC
             LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at
             FROM bac_bottles
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row, ?string $currentId): array
    {
        $id = (string) $row['id'];

        return [
            'id' => $id,
            'volume_ml' => (float) $row['volume_ml'],
            'remaining_ml' => (float) $row['remaining_ml'],
            'opened_at' => (string) $row['opened_at'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at' => (string) $row['created_at'],
            'is_current' => $currentId !== null && $id === $currentId,
        ];
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
