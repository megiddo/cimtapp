<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

/**
 * Builds derived vial display names (`{Peptide} - {People} - {concentration}`).
 */
final class VialNamer
{
    /**
     * @param list<string> $personNames in sort order
     */
    public function build(string $peptideName, array $personNames, float $concentrationMgPerMl): string
    {
        $people = implode(', ', $personNames);

        return $peptideName . ' - ' . $people . ' - ' . LogBookFormatter::formatConcentration($concentrationMgPerMl);
    }
}