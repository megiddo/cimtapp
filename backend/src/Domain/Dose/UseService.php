<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class UseService
{
    public function __construct(
        private readonly DoseCalculator $doses,
        private readonly CompoundService $compounds,
        private readonly SyringeService $syringes,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, FieldParser $query): array
    {
        $limit = $this->limit($query);
        $before = $this->before($query);

        if ($before === null) {
            $stmt = $pdo->prepare(
                'SELECT uses.*, compounds.peptide_type_name, compounds.name AS compound_name
                 FROM uses
                 JOIN compounds ON compounds.id = uses.compound_id
                 ORDER BY uses.used_at DESC, uses.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare(
                'SELECT uses.*, compounds.peptide_type_name, compounds.name AS compound_name
                 FROM uses
                 JOIN compounds ON compounds.id = uses.compound_id
                 WHERE uses.used_at < :before
                 ORDER BY uses.used_at DESC, uses.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':before', $before);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT uses.*, compounds.peptide_type_name, compounds.name AS compound_name
             FROM uses
             JOIN compounds ON compounds.id = uses.compound_id
             WHERE uses.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainRecordNotFoundException(DoseConfig::USE_UNKNOWN);
        }

        return $this->map($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $compound = $this->compoundForCreate($pdo, $fields);
        $syringe = $this->syringeForWrite($pdo, $fields, true);
        $iu = $this->requireIu($fields);
        $usedAt = $fields->optionalDatetime('used_at') ?? $this->timestamp();
        $notes = $fields->optionalString('notes');
        $volumeMl = $this->doses->volumeMl(
            $iu,
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
        );
        $peptideMg = $this->doses->peptideMg(
            $iu,
            (float) $compound['peptide_mg'],
            (float) $compound['bac_water_ml'],
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
        );
        $this->assertNotOverdraw($pdo, $compound, $peptideMg, $iu, $syringe, null);

        $id = $this->ids->uuid();
        $now = $this->timestamp();
        $stmt = $pdo->prepare(
            'INSERT INTO uses (
                id, compound_id, iu, syringe_id, syringe_label, syringe_volume_ml, syringe_capacity_iu,
                volume_ml, peptide_mg, used_at, notes, created_at, updated_at
             ) VALUES (
                :id, :compound_id, :iu, :syringe_id, :syringe_label, :syringe_volume_ml, :syringe_capacity_iu,
                :volume_ml, :peptide_mg, :used_at, :notes, :created_at, :updated_at
             )'
        );
        $stmt->execute([
            ':id' => $id,
            ':compound_id' => $compound['id'],
            ':iu' => $iu,
            ':syringe_id' => $syringe['id'],
            ':syringe_label' => $syringe['label'],
            ':syringe_volume_ml' => $syringe['volume_ml'],
            ':syringe_capacity_iu' => $syringe['capacity_iu'],
            ':volume_ml' => $volumeMl,
            ':peptide_mg' => $peptideMg,
            ':used_at' => $usedAt,
            ':notes' => $notes,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * Recalculates mg against the original vial. Remainder check puts the original use back first.
     *
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $compound = $this->compounds->get($pdo, (string) $existing['compound_id']);
        $iu = $fields->has('iu') ? $this->requireIu($fields) : (float) $existing['iu'];
        $usedAt = $fields->has('used_at')
            ? $fields->requireDatetime('used_at')
            : (string) $existing['used_at'];
        $notes = $fields->has('notes') ? $fields->optionalString('notes') : $existing['notes'];

        if ($fields->has('syringe_id')) {
            $syringe = $this->syringeForWrite($pdo, $fields, false);
        } else {
            $syringe = [
                'id' => $existing['syringe_id'],
                'label' => $existing['syringe_label'],
                'volume_ml' => $existing['syringe_volume_ml'],
                'capacity_iu' => $existing['syringe_capacity_iu'],
            ];
        }

        $volumeMl = $this->doses->volumeMl($iu, (float) $syringe['volume_ml'], (float) $syringe['capacity_iu']);
        $peptideMg = $this->doses->peptideMg(
            $iu,
            (float) $compound['peptide_mg'],
            (float) $compound['bac_water_ml'],
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
        );
        $this->assertNotOverdraw($pdo, $compound, $peptideMg, $iu, $syringe, $id);

        $stmt = $pdo->prepare(
            'UPDATE uses SET
                iu = :iu,
                syringe_id = :syringe_id,
                syringe_label = :syringe_label,
                syringe_volume_ml = :syringe_volume_ml,
                syringe_capacity_iu = :syringe_capacity_iu,
                volume_ml = :volume_ml,
                peptide_mg = :peptide_mg,
                used_at = :used_at,
                notes = :notes,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':iu' => $iu,
            ':syringe_id' => $syringe['id'],
            ':syringe_label' => $syringe['label'],
            ':syringe_volume_ml' => $syringe['volume_ml'],
            ':syringe_capacity_iu' => $syringe['capacity_iu'],
            ':volume_ml' => $volumeMl,
            ':peptide_mg' => $peptideMg,
            ':used_at' => $usedAt,
            ':notes' => $notes,
            ':updated_at' => $this->timestamp(),
        ]);

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->get($pdo, $id);
        $stmt = $pdo->prepare('DELETE FROM uses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function syringeForWrite(PDO $pdo, FieldParser $fields, bool $create): array
    {
        if ($fields->has('syringe_id') && $fields->optionalString('syringe_id') === null) {
            return $this->syringes->fallbackProfile();
        }

        if ($create) {
            return $this->syringes->syringeForNewUse($pdo, $fields->optionalString('syringe_id'));
        }

        $syringeId = $fields->optionalString('syringe_id');
        if ($syringeId === null) {
            return $this->syringes->fallbackProfile();
        }

        return $this->syringes->get($pdo, $syringeId);
    }

    /**
     * @return array<string, mixed>
     */
    private function compoundForCreate(PDO $pdo, FieldParser $fields): array
    {
        $compoundId = $fields->optionalString('compound_id');
        if ($compoundId !== null) {
            $found = $this->compounds->find($pdo, $compoundId);
            if ($found === null) {
                throw new ValidationException(['compound_id' => [DoseConfig::COMPOUND_UNKNOWN]]);
            }

            return $found;
        }

        try {
            return $this->compounds->current($pdo);
        } catch (DomainRecordNotFoundException) {
            throw new ValidationException(['compound_id' => [DoseConfig::NO_COMPOUND]]);
        }
    }

    /**
     * @param array<string, mixed> $compound
     * @param array<string, mixed> $syringe
     */
    private function assertNotOverdraw(
        PDO $pdo,
        array $compound,
        float $doseMg,
        float $iu,
        array $syringe,
        ?string $excludeUseId,
    ): void {
        $used = $this->compounds->usedPeptideMg($pdo, (string) $compound['id'], $excludeUseId);
        $remainder = $this->doses->remaining(
            (float) $compound['peptide_mg'],
            $used,
            (float) $compound['bac_water_ml'],
            (float) $syringe['volume_ml'],
            (float) $syringe['capacity_iu'],
        );
        if (!$this->doses->exceedsRemainder($doseMg, $remainder->remainingMg)) {
            return;
        }

        $remainingIu = $remainder->remainingIu;
        $message = DoseConfig::overdraw(DoseConfig::formatIu($iu), DoseConfig::formatIu($remainingIu));
        throw new ValidationException(['iu' => [$message]], $message, $remainingIu);
    }

    private function requireIu(FieldParser $fields): float
    {
        $iu = $fields->requireFloat('iu');
        $this->doses->assertIu($iu);

        return round($iu, DoseConfig::IU_DECIMALS);
    }

    private function before(FieldParser $query): ?string
    {
        try {
            return $query->optionalDatetime('before');
        } catch (ValidationException) {
            throw new ValidationException(['before' => [DoseConfig::BEFORE_INVALID]]);
        }
    }

    private function limit(FieldParser $query): int
    {
        if (!$query->has('limit')) {
            return DoseConfig::USES_DEFAULT_LIMIT;
        }

        $limit = (int) $query->requireFloat('limit');
        if ($limit < 1) {
            throw new ValidationException(['limit' => [DoseConfig::LIMIT_INVALID]]);
        }
        if ($limit > DoseConfig::USES_MAX_LIMIT) {
            return DoseConfig::USES_MAX_LIMIT;
        }

        return $limit;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'compound_id' => (string) $row['compound_id'],
            'peptide_type_name' => (string) $row['peptide_type_name'],
            'compound_name' => (string) $row['compound_name'],
            'iu' => (float) $row['iu'],
            'syringe_id' => $row['syringe_id'] === null ? null : (string) $row['syringe_id'],
            'syringe_label' => $row['syringe_label'] === null ? null : (string) $row['syringe_label'],
            'syringe_volume_ml' => (float) $row['syringe_volume_ml'],
            'syringe_capacity_iu' => (float) $row['syringe_capacity_iu'],
            'volume_ml' => (float) $row['volume_ml'],
            'peptide_mg' => (float) $row['peptide_mg'],
            'used_at' => (string) $row['used_at'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
