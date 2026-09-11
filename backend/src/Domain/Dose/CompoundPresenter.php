<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class CompoundPresenter
{
    public function __construct(
        private readonly CompoundRepository $compounds,
        private readonly DoseCalculator $doses,
        private readonly ProfileService $profiles,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $syringe
     * @return array<string, mixed>
     */
    public function present(PDO $pdo, array $row, array $syringe): array
    {
        $id = (string) $row['id'];
        $peptideMg = (float) $row['peptide_mg'];
        $bacWaterMl = (float) $row['bac_water_ml'];
        $adjustmentMg = $this->compounds->adjustmentPeptideMg($pdo, $id);
        $remainder = $this->doses->remaining(
            $peptideMg,
            $this->compounds->usedPeptideMg($pdo, $id),
            $bacWaterMl,
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
            $adjustmentMg,
        );
        $archivedAt = !isset($row['archived_at']) || $row['archived_at'] === null || $row['archived_at'] === ''
            ? null
            : (string) $row['archived_at'];

        return [
            'id' => $id,
            'name' => (string) $row['name'],
            'is_open' => $archivedAt === null && (int) ($row['is_open'] ?? 1) === 1,
            'archived_at' => $archivedAt,
            'peptide_type_id' => (string) $row['peptide_type_id'],
            'peptide_type_slug' => (string) $row['peptide_type_slug'],
            'peptide_type_name' => (string) $row['peptide_type_name'],
            'peptide_mg' => $peptideMg,
            'bac_water_ml' => $bacWaterMl,
            'compounded_at' => (string) $row['compounded_at'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at' => (string) $row['created_at'],
            'bac_bottle_id' => $row['bac_bottle_id'] === null ? null : (string) $row['bac_bottle_id'],
            'has_uses' => $this->compounds->hasUses($pdo, $id),
            'adjustment_mg' => $adjustmentMg,
            'remaining_mg' => $remainder->remainingMg,
            'remaining_ml' => $remainder->remainingMl,
            'remaining_iu' => $remainder->remainingIu,
            'concentration' => $remainder->concentration,
            'profile_ids' => $this->profiles->profileIdsForCompound($pdo, $id),
        ];
    }

    /**
     * @param array<string, mixed> $presented
     * @return array<string, mixed>
     */
    public function remainderSummary(array $presented): array
    {
        return [
            'compound_id' => $presented['id'],
            'name' => $presented['name'],
            'peptide_name' => $presented['peptide_type_name'],
            'remaining_mg' => $presented['remaining_mg'],
            'remaining_ml' => $presented['remaining_ml'],
            'remaining_iu' => $presented['remaining_iu'],
            'concentration' => $presented['concentration'],
            'compounded_at' => $presented['compounded_at'],
            'is_open' => $presented['is_open'],
        ];
    }
}
