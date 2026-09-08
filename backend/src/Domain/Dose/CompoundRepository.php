<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class CompoundRepository
{
    public function __construct(private readonly DoseCalculator $doses)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRows(PDO $pdo, StockList $query): array
    {
        $sql = $query->includesArchived()
            ? 'SELECT * FROM compounds ORDER BY (archived_at IS NULL) DESC, compounded_at DESC, id DESC'
            : 'SELECT * FROM compounds WHERE archived_at IS NULL ORDER BY compounded_at DESC, id DESC';
        $stmt = $pdo->query($sql);
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT * FROM compounds WHERE archived_at IS NULL ORDER BY compounded_at DESC, id DESC LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRow(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM compounds WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at, bac_bottle_id,
                name, is_open
             ) VALUES (
                :id, :peptide_type_id, :peptide_type_slug, :peptide_type_name,
                :peptide_mg, :bac_water_ml, :compounded_at, :notes, :created_at, :bac_bottle_id,
                :name, :is_open
             )'
        );
        $stmt->execute($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'UPDATE compounds SET
                peptide_type_id = :peptide_type_id,
                peptide_type_slug = :peptide_type_slug,
                peptide_type_name = :peptide_type_name,
                peptide_mg = :peptide_mg,
                bac_water_ml = :bac_water_ml,
                compounded_at = :compounded_at,
                notes = :notes,
                bac_bottle_id = :bac_bottle_id,
                name = :name
             WHERE id = :id'
        );
        $stmt->execute($values);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $unlink = $pdo->prepare('DELETE FROM compound_profiles WHERE compound_id = :id');
        $unlink->execute([':id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM compounds WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function usedPeptideMg(PDO $pdo, string $compoundId, ?string $excludeUseId = null): float
    {
        if ($excludeUseId === null) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(peptide_mg), 0) FROM uses WHERE compound_id = :id');
            $stmt->execute([':id' => $compoundId]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(peptide_mg), 0) FROM uses WHERE compound_id = :id AND id != :exclude'
            );
            $stmt->execute([':id' => $compoundId, ':exclude' => $excludeUseId]);
        }

        return $this->doses->roundMg((float) $stmt->fetchColumn());
    }

    public function adjustmentPeptideMg(PDO $pdo, string $compoundId): float
    {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(delta_mg), 0) FROM compound_adjustments WHERE compound_id = :id');
        $stmt->execute([':id' => $compoundId]);

        return $this->doses->roundMg((float) $stmt->fetchColumn());
    }

    public function hasUses(PDO $pdo, string $compoundId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM uses WHERE compound_id = :id LIMIT 1');
        $stmt->execute([':id' => $compoundId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertAdjustment(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO compound_adjustments (id, compound_id, delta_mg, remaining_ml, notes, created_at)
             VALUES (:id, :compound_id, :delta_mg, :remaining_ml, :notes, :created_at)'
        );
        $stmt->execute($values);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function useDoseRows(PDO $pdo, string $compoundId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, iu, syringe_volume_ml, syringe_capacity_iu FROM uses WHERE compound_id = :id'
        );
        $stmt->execute([':id' => $compoundId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateUseDose(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'UPDATE uses SET volume_ml = :volume_ml, peptide_mg = :peptide_mg, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute($values);
    }
}
