<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class CompoundService
{
    public function __construct(
        private readonly DoseCalculator $doses,
        private readonly UserPeptideService $peptides,
        private readonly SyringeService $syringes,
        private readonly BacBottleService $bacBottles,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT * FROM compounds ORDER BY compounded_at DESC, id DESC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        $default = $this->syringes->defaultSyringe($pdo);

        return array_values(array_map(
            fn (array $row): array => $this->present($pdo, $row, $default),
            is_array($rows) ? $rows : [],
        ));
    }

    /**
     * Latest compounded_at (not created_at). 404 when none.
     *
     * @return array<string, mixed>
     */
    public function current(PDO $pdo): array
    {
        $row = $this->currentRow($pdo);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }

        return $this->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
    }

    /**
     * Remainder summary for GET /me. Null when no vial exists.
     *
     * @return array<string, mixed>|null
     */
    public function currentRemainder(PDO $pdo): ?array
    {
        $row = $this->currentRow($pdo);
        if ($row === null) {
            return null;
        }
        $presented = $this->present($pdo, $row, $this->syringes->defaultSyringe($pdo));

        return [
            'compound_id' => $presented['id'],
            'peptide_name' => $presented['peptide_type_name'],
            'remaining_mg' => $presented['remaining_mg'],
            'remaining_ml' => $presented['remaining_ml'],
            'remaining_iu' => $presented['remaining_iu'],
            'concentration' => $presented['concentration'],
            'compounded_at' => $presented['compounded_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }

        return $this->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        $row = $this->findRow($pdo, $id);

        return $row === null ? null : $this->present($pdo, $row, $this->syringes->defaultSyringe($pdo));
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

    public function hasUses(PDO $pdo, string $compoundId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM uses WHERE compound_id = :id LIMIT 1');
        $stmt->execute([':id' => $compoundId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $peptide = $this->peptides->require($pdo, $fields->requireString('peptide_type_id'));
        $peptideMg = $this->doses->roundMg($fields->requirePositiveFloat('peptide_mg'));
        $bacWaterMl = $fields->requirePositiveFloat('bac_water_ml');
        $compoundedAt = $fields->requireDatetime('compounded_at');
        $notes = $fields->optionalString('notes');
        $id = $this->ids->uuid();
        $now = $this->timestamp();
        $bottleId = $this->bacBottles->debitCurrent($pdo, $bacWaterMl);

        $stmt = $pdo->prepare(
            'INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at, bac_bottle_id
             ) VALUES (
                :id, :peptide_type_id, :peptide_type_slug, :peptide_type_name,
                :peptide_mg, :bac_water_ml, :compounded_at, :notes, :created_at, :bac_bottle_id
             )'
        );
        $stmt->execute([
            ':id' => $id,
            ':peptide_type_id' => $peptide['id'],
            ':peptide_type_slug' => $peptide['slug'],
            ':peptide_type_name' => $peptide['name'],
            ':peptide_mg' => $peptideMg,
            ':bac_water_ml' => $bacWaterMl,
            ':compounded_at' => $compoundedAt,
            ':notes' => $notes,
            ':created_at' => $now,
            ':bac_bottle_id' => $bottleId,
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * Mix fields stay editable after uses. Changing mg or BAC recalculates stored use mg.
     *
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);

        $peptideTypeId = $fields->has('peptide_type_id')
            ? $fields->requireString('peptide_type_id')
            : (string) $existing['peptide_type_id'];
        $peptideMg = $fields->has('peptide_mg')
            ? $this->doses->roundMg($fields->requirePositiveFloat('peptide_mg'))
            : $this->doses->roundMg((float) $existing['peptide_mg']);
        $bacWaterMl = $fields->has('bac_water_ml')
            ? $fields->requirePositiveFloat('bac_water_ml')
            : (float) $existing['bac_water_ml'];
        $compoundedAt = $fields->has('compounded_at')
            ? $fields->requireDatetime('compounded_at')
            : (string) $existing['compounded_at'];
        $notes = $fields->has('notes') ? $fields->optionalString('notes') : $existing['notes'];

        $mixChanged = $this->doses->roundMg((float) $existing['peptide_mg']) !== $peptideMg
            || abs((float) $existing['bac_water_ml'] - $bacWaterMl) > 1e-9;
        if ($mixChanged) {
            $this->assertMixFitsUses($pdo, $id, $peptideMg, $bacWaterMl);
        }

        $bottleId = $existing['bac_bottle_id'] === null ? null : (string) $existing['bac_bottle_id'];
        if (abs((float) $existing['bac_water_ml'] - $bacWaterMl) > 1e-9) {
            $bottleId = $this->bacBottles->applyMixDelta(
                $pdo,
                $bottleId,
                (float) $existing['bac_water_ml'],
                $bacWaterMl,
            );
        }

        $peptide = $this->peptides->require($pdo, $peptideTypeId);

        $stmt = $pdo->prepare(
            'UPDATE compounds SET
                peptide_type_id = :peptide_type_id,
                peptide_type_slug = :peptide_type_slug,
                peptide_type_name = :peptide_type_name,
                peptide_mg = :peptide_mg,
                bac_water_ml = :bac_water_ml,
                compounded_at = :compounded_at,
                notes = :notes,
                bac_bottle_id = :bac_bottle_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':peptide_type_id' => $peptide['id'],
            ':peptide_type_slug' => $peptide['slug'],
            ':peptide_type_name' => $peptide['name'],
            ':peptide_mg' => $peptideMg,
            ':bac_water_ml' => $bacWaterMl,
            ':compounded_at' => $compoundedAt,
            ':notes' => $notes,
            ':bac_bottle_id' => $bottleId,
        ]);

        if ($mixChanged) {
            $this->syncUseDoses($pdo, $id, $peptideMg, $bacWaterMl);
        }

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $row = $this->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }
        if ($this->hasUses($pdo, $id)) {
            throw new ValidationException(['id' => [DoseConfig::COMPOUND_HAS_USES]], DoseConfig::COMPOUND_HAS_USES);
        }

        $bottleId = $row['bac_bottle_id'] === null ? null : (string) $row['bac_bottle_id'];
        $this->bacBottles->credit($pdo, $bottleId, (float) $row['bac_water_ml']);

        $stmt = $pdo->prepare('DELETE FROM compounds WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT * FROM compounds ORDER BY compounded_at DESC, id DESC LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM compounds WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $syringe
     * @return array<string, mixed>
     */
    private function present(PDO $pdo, array $row, array $syringe): array
    {
        $id = (string) $row['id'];
        $peptideMg = (float) $row['peptide_mg'];
        $bacWaterMl = (float) $row['bac_water_ml'];
        $remainder = $this->doses->remaining(
            $peptideMg,
            $this->usedPeptideMg($pdo, $id),
            $bacWaterMl,
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
        );

        return [
            'id' => $id,
            'peptide_type_id' => (string) $row['peptide_type_id'],
            'peptide_type_slug' => (string) $row['peptide_type_slug'],
            'peptide_type_name' => (string) $row['peptide_type_name'],
            'peptide_mg' => $peptideMg,
            'bac_water_ml' => $bacWaterMl,
            'compounded_at' => (string) $row['compounded_at'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at' => (string) $row['created_at'],
            'bac_bottle_id' => $row['bac_bottle_id'] === null ? null : (string) $row['bac_bottle_id'],
            'has_uses' => $this->hasUses($pdo, $id),
            'remaining_mg' => $remainder->remainingMg,
            'remaining_ml' => $remainder->remainingMl,
            'remaining_iu' => $remainder->remainingIu,
            'concentration' => $remainder->concentration,
        ];
    }

    private function assertMixFitsUses(PDO $pdo, string $compoundId, float $peptideMg, float $bacWaterMl): void
    {
        $used = $this->doses->roundMg($this->recalculatedUsedMg($pdo, $compoundId, $peptideMg, $bacWaterMl));
        if (!$this->doses->exceedsRemainder($used, $peptideMg)) {
            return;
        }

        throw new ValidationException(
            ['peptide_mg' => [DoseConfig::COMPOUND_OVERDRAW]],
            DoseConfig::COMPOUND_OVERDRAW,
        );
    }

    private function syncUseDoses(PDO $pdo, string $compoundId, float $peptideMg, float $bacWaterMl): void
    {
        $now = $this->timestamp();
        $update = $pdo->prepare(
            'UPDATE uses SET volume_ml = :volume_ml, peptide_mg = :peptide_mg, updated_at = :updated_at WHERE id = :id'
        );
        foreach ($this->useDoseRows($pdo, $compoundId) as $row) {
            $iu = (float) $row['iu'];
            $volumeMl = (float) $row['syringe_volume_ml'];
            $capacityIu = (float) $row['syringe_capacity_iu'];
            $update->execute([
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
        foreach ($this->useDoseRows($pdo, $compoundId) as $row) {
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

    /**
     * @return list<array<string, mixed>>
     */
    private function useDoseRows(PDO $pdo, string $compoundId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, iu, syringe_volume_ml, syringe_capacity_iu FROM uses WHERE compound_id = :id'
        );
        $stmt->execute([':id' => $compoundId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
