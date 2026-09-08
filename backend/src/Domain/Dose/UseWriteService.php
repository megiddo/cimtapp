<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;

final class UseWriteService
{
    public function __construct(
        private readonly UseRepository $uses,
        private readonly DoseCalculator $doses,
        private readonly CompoundService $compounds,
        private readonly SyringeService $syringes,
        private readonly ProfileService $profiles,
        private readonly MigrateUseProfile $migrateProfile,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $compound = $this->compoundForCreate($pdo, $fields);
        $profile = $this->profileForCreate($pdo, $fields, (string) $compound['id']);
        $syringe = $this->syringeForWrite($pdo, $fields, true, (string) $profile['id']);
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
        $this->uses->insert($pdo, [
            ':id' => $id,
            ':compound_id' => $compound['id'],
            ':profile_id' => $profile['id'],
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

        return $this->uses->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->uses->get($pdo, $id);
        $compound = $this->compounds->get($pdo, (string) $existing['compound_id']);
        if ($compound['archived_at'] !== null) {
            throw new ValidationException(['id' => [DoseConfig::COMPOUND_ARCHIVED]], DoseConfig::COMPOUND_ARCHIVED);
        }
        $iu = $fields->has('iu') ? $this->requireIu($fields) : (float) $existing['iu'];
        $usedAt = $fields->has('used_at')
            ? $fields->requireDatetime('used_at')
            : (string) $existing['used_at'];
        $notes = $fields->has('notes') ? $fields->optionalString('notes') : $existing['notes'];
        $profileId = $this->migrateProfile->profileIdForPatch($pdo, $fields, (string) $existing['profile_id']);

        if ($fields->has('syringe_id')) {
            $syringe = $this->syringeForWrite($pdo, $fields, false, $profileId);
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

        $this->uses->update($pdo, [
            ':id' => $id,
            ':profile_id' => $profileId,
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

        return $this->uses->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $existing = $this->uses->get($pdo, $id);
        $compound = $this->compounds->get($pdo, (string) $existing['compound_id']);
        if ($compound['archived_at'] !== null) {
            throw new ValidationException(['id' => [DoseConfig::COMPOUND_ARCHIVED]], DoseConfig::COMPOUND_ARCHIVED);
        }
        $this->uses->delete($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function syringeForWrite(PDO $pdo, FieldParser $fields, bool $create, string $profileId): array
    {
        if ($fields->has('syringe_id') && $fields->optionalString('syringe_id') === null) {
            return $this->syringes->fallbackProfile();
        }

        if ($create) {
            return $this->syringes->syringeForNewUse($pdo, $fields->optionalString('syringe_id'), $profileId);
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
    private function profileForCreate(PDO $pdo, FieldParser $fields, string $compoundId): array
    {
        $profileId = $fields->optionalString('profile_id');
        $profile = $profileId === null
            ? $this->profiles->defaultProfile($pdo)
            : $this->profiles->require($pdo, $profileId);

        if (!$this->profiles->compoundLinked($pdo, $compoundId, (string) $profile['id'])) {
            throw new ValidationException(['compound_id' => [DoseConfig::PROFILE_VIAL_MISMATCH]]);
        }

        return $profile;
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
            if ($found['archived_at'] !== null) {
                throw new ValidationException(['compound_id' => [DoseConfig::COMPOUND_ARCHIVED]]);
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
            $this->compounds->adjustmentPeptideMg($pdo, (string) $compound['id']),
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

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
