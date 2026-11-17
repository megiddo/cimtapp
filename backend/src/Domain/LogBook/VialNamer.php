<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

/**
 * Builds derived vial display names (`{Peptide} - {People} - {mg per IU}`).
 */
final class VialNamer
{
    /**
     * @param list<string> $personNames in sort order
     */
    public function build(
        string $peptideName,
        array $personNames,
        float $concentrationMgPerMl,
        float $syringeVolumeMl,
        float $syringeCapacityIu,
    ): string {
        if ($syringeVolumeMl <= 0.0 || $syringeCapacityIu <= 0.0) {
            throw new \InvalidArgumentException('Syringe volume and capacity must be positive.');
        }
        $iuPerMl = $syringeCapacityIu / $syringeVolumeMl;
        $mgPerIu = $concentrationMgPerMl / $iuPerMl;
        $people = implode(', ', $personNames);

        return $peptideName . ' - ' . $people . ' - ' . LogBookFormatter::formatMg($mgPerIu) . ' mg/IU';
    }
}