<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;
use PDO;

final class BacLedger
{
    public function __construct(
        private readonly BacBottleRepository $bottles,
        private readonly DoseCalculator $doses,
    ) {
    }

    public function debitCurrent(PDO $pdo, float $ml): ?string
    {
        $row = $this->bottles->currentRow($pdo);
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

            $row = $this->bottles->findRow($pdo, $bottleId);
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
        $row = $this->bottles->findRow($pdo, $bottleId);
        if ($row === null) {
            return;
        }

        $volume = (float) $row['volume_ml'];
        $remaining = $this->doses->roundVolume((float) $row['remaining_ml'] + $ml);
        if ($remaining > $volume) {
            $remaining = $volume;
        }
        $this->bottles->setRemaining($pdo, $bottleId, $remaining);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function debitRow(PDO $pdo, array $row, float $ml, string $field = 'bac_water_ml'): string
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

        $this->bottles->setRemaining($pdo, (string) $row['id'], $this->doses->roundVolume($remaining - $ml));

        return (string) $row['id'];
    }
}
