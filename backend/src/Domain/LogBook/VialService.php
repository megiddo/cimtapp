<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use DateTimeZone;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Current vial lifecycle: create, inventory totals, probably-empty.
 */
final class VialService
{
    public function __construct(
        private readonly VialRepository $vials,
        private readonly VialPeopleRepository $vialPeople,
        private readonly VialAssignmentService $assignment,
        private readonly LidColorRepository $lidColors,
        private readonly LogPeptideService $peptides,
        private readonly PersonRepository $people,
        private readonly DoseCalculator $doses,
        private readonly VialNamer $namer,
        private readonly AccountSyringeRepository $accountSyringe,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCurrent(PDO $pdo): array
    {
        $rows = $this->vials->listCurrent($pdo);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($pdo, $row);
        }

        return $out;
    }

    public function create(PDO $pdo, FieldParser $fields): array
    {
        $peptide = $this->peptides->requireVisible($pdo, $fields->requireString('peptide_id'));
        $personIds = $fields->optionalIdList('person_ids');
        if ($personIds === null || $personIds === []) {
            throw new ValidationException(['person_ids' => [LogBookConfig::PERSON_IDS_REQUIRED]]);
        }
        foreach ($personIds as $personId) {
            $person = $this->people->find($pdo, $personId);
            if ($person === null || $person['removed_at'] !== null) {
                throw new ValidationException(['person_ids' => [LogBookConfig::PERSON_UNKNOWN]]);
            }
        }
        $peptideMg = $fields->requirePositiveFloat('peptide_mg');
        $bacWaterMl = $fields->requirePositiveFloat('bac_water_ml');
        $concentration = $this->doses->concentration($peptideMg, $bacWaterMl);
        [$lidId, $lidName] = $this->resolveLidColor($pdo, $fields);
        $names = [];
        foreach ($personIds as $personId) {
            $names[] = (string) ($this->people->find($pdo, $personId)['name'] ?? '');
        }
        $syringe = $this->accountSyringe->get($pdo);
        $displayName = $this->namer->build(
            $peptide['name'],
            $names,
            $concentration,
            $syringe['volume_ml'],
            $syringe['capacity_iu'],
        );
        $id = $this->ids->uuid();
        $now = $this->timestamp();
        $this->vials->insert(
            $pdo,
            $id,
            $peptide['id'],
            $peptide['name'],
            $peptideMg,
            $bacWaterMl,
            $concentration,
            $displayName,
            $lidId,
            $lidName,
            $now,
            $now,
        );
        $prior = [];
        foreach ($personIds as $personId) {
            $old = $this->vials->currentForPerson($pdo, $personId);
            if ($old !== null && $old !== $id) {
                $prior[] = ['person_id' => $personId, 'previous_vial_id' => $old];
            }
        }
        $this->assignment->assignAsCurrent($pdo, $id, $personIds);
        $this->logger->info('logbook.vial.created', ['vial_id' => $id, 'unassigned' => $prior]);
        $row = $this->vials->find($pdo, $id);
        $this->assert(is_array($row));

        return $this->present($pdo, $row);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PDO $pdo, array $row): array
    {
        $vialId = (string) $row['id'];
        $used = $this->vials->sumDoseMg($pdo, $vialId);
        $mgLeft = LogBookFormatter::roundMg(
            (float) $row['peptide_mg'] - (float) $row['usage_offset_mg'] - $used,
        );
        $people = $this->vialPeople->listForVial($pdo, $vialId);
        $drawn = $this->vials->drawnMgByPerson($pdo, $vialId);

        return $row + [
            'mg_left' => $mgLeft,
            'drawn_mg' => $drawn,
            'probably_empty' => $this->probablyEmpty($pdo, $vialId),
            'people' => array_map(static fn (array $p): array => [
                'person_id' => $p['person_id'],
                'name' => $p['name'],
                'drawn_mg' => $drawn[$p['person_id']] ?? 0.0,
            ], $people),
        ];
    }

    public function requireCurrent(PDO $pdo, string $id): array
    {
        if (!$this->vials->isCurrent($pdo, $id)) {
            throw new ValidationException(['vial_id' => [LogBookConfig::VIAL_NOT_CURRENT]]);
        }
        $row = $this->vials->find($pdo, $id);
        if ($row === null) {
            throw new ValidationException(['vial_id' => [LogBookConfig::VIAL_UNKNOWN]]);
        }

        return $row;
    }

    public function probablyEmpty(PDO $pdo, string $vialId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT is_last_draw FROM log_entries WHERE vial_id = :id ORDER BY logged_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':id' => $vialId]);
        $flag = $stmt->fetchColumn();
        if ($flag === false) {
            return false;
        }

        return (int) $flag === 1;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolveLidColor(PDO $pdo, FieldParser $fields): array
    {
        $lidId = $fields->optionalString('lid_color_id');
        $lidName = $fields->optionalString('lid_color_name');
        if ($lidId !== null) {
            $row = $this->lidColors->findById($pdo, $lidId);
            if ($row === null) {
                throw new ValidationException(['lid_color_id' => [LogBookConfig::LID_COLOR_UNKNOWN]]);
            }

            return [$lidId, $row['name']];
        }
        if ($lidName !== null) {
            $existing = $this->lidColors->findByNameCi($pdo, $lidName);
            if ($existing !== null) {
                return [$existing['id'], $existing['name']];
            }
            $id = $this->ids->uuid();
            $this->lidColors->insert($pdo, $id, $lidName, $this->timestamp());

            return [$id, $lidName];
        }
        throw new ValidationException(['lid_color_name' => [LogBookConfig::LID_COLOR_REQUIRED]]);
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function assert(bool $condition): void
    {
        if (!$condition) {
            throw new \RuntimeException('Invariant failed.');
        }
    }
}