<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use DateTimeZone;
use PDO;

final class CompoundAdjustmentService
{
    public function __construct(
        private readonly CompoundRepository $compounds,
        private readonly CompoundQueryService $queries,
        private readonly DoseCalculator $doses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function adjust(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->queries->get($pdo, $id);
        if ($existing['archived_at'] !== null) {
            throw new ValidationException(['id' => [DoseConfig::COMPOUND_ARCHIVED]], DoseConfig::COMPOUND_ARCHIVED);
        }

        $targetMl = $this->doses->roundVolume($fields->requireNonNegativeFloat('remaining_ml'));
        $mixMl = (float) $existing['bac_water_ml'];
        if ($targetMl - $mixMl > 1e-9) {
            throw new ValidationException(
                ['remaining_ml' => [DoseConfig::REMAINING_EXCEEDS_MIX]],
                DoseConfig::REMAINING_EXCEEDS_MIX,
            );
        }

        $concentration = (float) $existing['concentration'];
        $targetMg = $this->doses->roundMg($targetMl * $concentration);
        $deltaMg = $this->doses->roundMg($targetMg - (float) $existing['remaining_mg']);
        if (abs($deltaMg) < 1e-9) {
            return $existing;
        }

        $this->compounds->insertAdjustment($pdo, [
            ':id' => $this->ids->uuid(),
            ':compound_id' => $id,
            ':delta_mg' => $deltaMg,
            ':remaining_ml' => $targetMl,
            ':notes' => $fields->optionalString('notes'),
            ':created_at' => $this->timestamp(),
        ]);

        return $this->queries->get($pdo, $id);
    }

    public function assertMixFitsUses(PDO $pdo, string $compoundId, float $peptideMg, float $bacWaterMl): void
    {
        $used = $this->doses->roundMg($this->recalculatedUsedMg($pdo, $compoundId, $peptideMg, $bacWaterMl));
        $netUsed = $this->doses->roundMg($used - $this->compounds->adjustmentPeptideMg($pdo, $compoundId));
        if (!$this->doses->exceedsRemainder($netUsed, $peptideMg)) {
            return;
        }

        throw new ValidationException(
            ['peptide_mg' => [DoseConfig::COMPOUND_OVERDRAW]],
            DoseConfig::COMPOUND_OVERDRAW,
        );
    }

    public function syncUseDoses(PDO $pdo, string $compoundId, float $peptideMg, float $bacWaterMl): void
    {
        $now = $this->timestamp();
        foreach ($this->compounds->useDoseRows($pdo, $compoundId) as $row) {
            $iu = (float) $row['iu'];
            $volumeMl = (float) $row['syringe_volume_ml'];
            $capacityIu = (float) $row['syringe_capacity_iu'];
            $this->compounds->updateUseDose($pdo, [
                ':id' => $row['id'],
                ':volume_ml' => $this->doses->volumeMl($iu, $volumeMl, $capacityIu),
                ':peptide_mg' => $this->doses->peptideMg($iu, $peptideMg, $bacWaterMl, $volumeMl, $capacityIu),
                ':updated_at' => $now,
            ]);
        }
    }

    private function recalculatedUsedMg(
        PDO $pdo,
        string $compoundId,
        float $peptideMg,
        float $bacWaterMl,
    ): float {
        $used = 0.0;
        foreach ($this->compounds->useDoseRows($pdo, $compoundId) as $row) {
            $used += $this->doses->peptideMg(
                (float) $row['iu'],
                $peptideMg,
                $bacWaterMl,
                (float) $row['syringe_volume_ml'],
                (float) $row['syringe_capacity_iu'],
            );
        }

        return $used;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
