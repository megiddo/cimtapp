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
        private readonly PeptideCatalog $peptides,
        private readonly SyringeService $syringes,
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
        $peptide = $this->requirePeptide($fields->requireString('peptide_type_id'));
        $peptideMg = $this->doses->roundMg($fields->requirePositiveFloat('peptide_mg'));
        $bacWaterMl = $fields->requirePositiveFloat('bac_water_ml');
        $compoundedAt = $fields->requireDatetime('compounded_at');
        $notes = $fields->optionalString('notes');
        $id = $this->ids->uuid();
        $now = $this->timestamp();

        $stmt = $pdo->prepare(
            'INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at
             ) VALUES (
                :id, :peptide_type_id, :peptide_type_slug, :peptide_type_name,
                :peptide_mg, :bac_water_ml, :compounded_at, :notes, :created_at
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
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $locked = $this->hasUses($pdo, $id);

        $peptideTypeId = $existing['peptide_type_id'];
        $peptideMg = $existing['peptide_mg'];
        $bacWaterMl = $existing['bac_water_ml'];
        $errors = [];

        if ($fields->has('peptide_type_id')) {
            $requested = $fields->requireString('peptide_type_id');
            if ($locked && $requested !== $peptideTypeId) {
                $errors['peptide_type_id'] = [DoseConfig::PEPTIDE_LOCKED];
            } elseif (!$locked) {
                $peptideTypeId = $requested;
            }
        }
        if ($fields->has('peptide_mg')) {
            $requestedMg = $this->doses->roundMg($fields->requirePositiveFloat('peptide_mg'));
            if ($locked && $this->doses->roundMg((float) $peptideMg) !== $requestedMg) {
                $errors['peptide_mg'] = [DoseConfig::MG_LOCKED];
            } elseif (!$locked) {
                $peptideMg = $requestedMg;
            }
        }
        if ($fields->has('bac_water_ml')) {
            $requestedBac = $fields->requirePositiveFloat('bac_water_ml');
            if ($locked && abs((float) $bacWaterMl - $requestedBac) > 1e-9) {
                $errors['bac_water_ml'] = [DoseConfig::BAC_LOCKED];
            } elseif (!$locked) {
                $bacWaterMl = $requestedBac;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $peptide = $this->requirePeptide((string) $peptideTypeId);
        $compoundedAt = $fields->has('compounded_at')
            ? $fields->requireDatetime('compounded_at')
            : (string) $existing['compounded_at'];
        $notes = $fields->has('notes') ? $fields->optionalString('notes') : $existing['notes'];

        $stmt = $pdo->prepare(
            'UPDATE compounds SET
                peptide_type_id = :peptide_type_id,
                peptide_type_slug = :peptide_type_slug,
                peptide_type_name = :peptide_type_name,
                peptide_mg = :peptide_mg,
                bac_water_ml = :bac_water_ml,
                compounded_at = :compounded_at,
                notes = :notes
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
        ]);

        return $this->get($pdo, $id);
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
            'has_uses' => $this->hasUses($pdo, $id),
            'remaining_mg' => $remainder->remainingMg,
            'remaining_ml' => $remainder->remainingMl,
            'remaining_iu' => $remainder->remainingIu,
            'concentration' => $remainder->concentration,
        ];
    }

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}
     */
    private function requirePeptide(string $id): array
    {
        $peptide = $this->peptides->findActiveById($id);
        if ($peptide === null) {
            throw new ValidationException(['peptide_type_id' => [DoseConfig::PEPTIDE_UNKNOWN]]);
        }

        return $peptide;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
