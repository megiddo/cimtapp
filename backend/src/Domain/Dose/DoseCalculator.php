<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;

final class DoseCalculator
{
    public function concentration(float $peptideMg, float $bacWaterMl): float
    {
        $this->assertPositive($peptideMg, 'peptide_mg');
        $this->assertPositive($bacWaterMl, 'bac_water_ml');

        return $peptideMg / $bacWaterMl;
    }

    public function mlPerIu(float $syringeVolumeMl, float $syringeCapacityIu): float
    {
        $this->assertPositive($syringeVolumeMl, 'volume_ml');
        $this->assertPositive($syringeCapacityIu, 'capacity_iu');

        return $syringeVolumeMl / $syringeCapacityIu;
    }

    public function volumeMl(float $iu, float $syringeVolumeMl, float $syringeCapacityIu): float
    {
        $this->assertIu($iu);

        return $this->roundVolume($iu * $this->mlPerIu($syringeVolumeMl, $syringeCapacityIu));
    }

    public function peptideMg(
        float $iu,
        float $compoundPeptideMg,
        float $bacWaterMl,
        float $syringeVolumeMl,
        float $syringeCapacityIu,
    ): float {
        $volume = $this->volumeMl($iu, $syringeVolumeMl, $syringeCapacityIu);

        return $this->roundMg($volume * $this->concentration($compoundPeptideMg, $bacWaterMl));
    }

    public function remaining(
        float $compoundPeptideMg,
        float $usedPeptideMgSum,
        float $bacWaterMl,
        float $syringeVolumeMl,
        float $syringeCapacityIu,
        float $adjustmentMg = 0.0,
    ): Remainder {
        $this->assertPositive($compoundPeptideMg, 'peptide_mg');
        $this->assertPositive($bacWaterMl, 'bac_water_ml');
        $concentration = $this->concentration($compoundPeptideMg, $bacWaterMl);
        $remainingMg = $this->roundMg($compoundPeptideMg - $usedPeptideMgSum + $adjustmentMg);
        $remainingMl = $this->roundVolume($remainingMg / $concentration);
        $remainingIu = round($remainingMl / $this->mlPerIu($syringeVolumeMl, $syringeCapacityIu), DoseConfig::IU_DECIMALS);

        return new Remainder($remainingMg, $remainingMl, $remainingIu, $concentration);
    }

    public function isDepleted(float $remaining): bool
    {
        return $remaining <= 1e-9;
    }

    public function exceedsRemainder(float $doseMg, float $remainingMg): bool
    {
        return $this->roundMg($doseMg) > $this->roundMg($remainingMg);
    }

    public function roundMg(float $mg): float
    {
        return round($mg, DoseConfig::MG_DECIMALS);
    }

    public function roundVolume(float $volumeMl): float
    {
        return round($volumeMl, DoseConfig::VOLUME_DECIMALS);
    }

    public function assertIu(float $iu): void
    {
        if ($iu <= 0.0) {
            throw new ValidationException(['iu' => [DoseConfig::IU_NOT_POSITIVE]]);
        }
        if (!$this->hasAtMostOneDecimal($iu)) {
            throw new ValidationException(['iu' => [DoseConfig::IU_ONE_DECIMAL]]);
        }
    }

    public function hasAtMostOneDecimal(float $value): bool
    {
        return abs($value * 10 - round($value * 10)) < 1e-6;
    }

    private function assertPositive(float $value, string $field): void
    {
        if ($value <= 0.0) {
            throw new ValidationException([$field => [DoseConfig::MUST_BE_POSITIVE]]);
        }
    }
}
