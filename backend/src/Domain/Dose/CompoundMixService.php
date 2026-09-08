<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class CompoundMixService
{
    public function __construct(
        private readonly CompoundRepository $compounds,
        private readonly CompoundQueryService $queries,
        private readonly CompoundAdjustmentService $adjustments,
        private readonly DoseCalculator $doses,
        private readonly UserPeptideService $peptides,
        private readonly BacBottleService $bacBottles,
        private readonly ProfileService $profiles,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
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
        $name = $this->vialName($fields, $peptide['name'], null);
        $profileIds = $this->profileIdsForWrite($pdo, $fields, true);
        if ($profileIds === null) {
            $profileIds = [(string) $this->profiles->defaultProfile($pdo)['id']];
        }
        $id = $this->ids->uuid();
        $now = $this->timestamp();
        $bottleId = $this->bacBottles->debitCurrent($pdo, $bacWaterMl);

        $this->compounds->insert($pdo, [
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
            ':name' => $name,
            ':is_open' => 1,
        ]);

        $this->profiles->replaceCompoundProfiles($pdo, $id, $profileIds);

        return $this->queries->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->queries->get($pdo, $id);

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
        $name = $this->vialName($fields, (string) $existing['peptide_type_name'], (string) $existing['name']);

        $mixChanged = $this->doses->roundMg((float) $existing['peptide_mg']) !== $peptideMg
            || abs((float) $existing['bac_water_ml'] - $bacWaterMl) > 1e-9;
        if ($mixChanged) {
            $this->adjustments->assertMixFitsUses($pdo, $id, $peptideMg, $bacWaterMl);
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
        $profileIds = $this->profileIdsForWrite($pdo, $fields, false);

        $this->compounds->update($pdo, [
            ':id' => $id,
            ':peptide_type_id' => $peptide['id'],
            ':peptide_type_slug' => $peptide['slug'],
            ':peptide_type_name' => $peptide['name'],
            ':peptide_mg' => $peptideMg,
            ':bac_water_ml' => $bacWaterMl,
            ':compounded_at' => $compoundedAt,
            ':notes' => $notes,
            ':bac_bottle_id' => $bottleId,
            ':name' => $name,
        ]);

        if ($mixChanged) {
            $this->adjustments->syncUseDoses($pdo, $id, $peptideMg, $bacWaterMl);
        }

        if ($profileIds !== null) {
            $this->profiles->replaceCompoundProfiles($pdo, $id, $profileIds);
        }

        return $this->queries->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $row = $this->compounds->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::COMPOUND_UNKNOWN);
        }
        if ($this->compounds->hasUses($pdo, $id)) {
            throw new ValidationException(['id' => [DoseConfig::COMPOUND_HAS_USES]], DoseConfig::COMPOUND_HAS_USES);
        }

        $bottleId = $row['bac_bottle_id'] === null ? null : (string) $row['bac_bottle_id'];
        $this->bacBottles->credit($pdo, $bottleId, (float) $row['bac_water_ml']);
        $this->compounds->delete($pdo, $id);
    }

    /**
     * @return list<string>|null
     */
    private function profileIdsForWrite(PDO $pdo, FieldParser $fields, bool $create): ?array
    {
        $ids = $fields->optionalIdList('profile_ids');
        if ($ids === null) {
            if (!$create) {
                return null;
            }

            return [(string) $this->profiles->defaultProfile($pdo)['id']];
        }

        return $this->profiles->requireIds($pdo, $ids);
    }

    private function vialName(FieldParser $fields, string $peptideName, ?string $existing): string
    {
        if (!$fields->has('name')) {
            return $existing ?? $peptideName;
        }

        $name = $fields->optionalString('name');
        if ($name === null) {
            throw new ValidationException(['name' => [DoseConfig::MUST_BE_TEXT]]);
        }
        if (strlen($name) > DoseConfig::VIAL_NAME_MAX) {
            throw new ValidationException(['name' => [DoseConfig::VIAL_NAME_TOO_LONG]]);
        }

        return $name;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
