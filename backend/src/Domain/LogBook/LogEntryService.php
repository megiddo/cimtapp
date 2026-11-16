<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use App\Domain\DomainException\DomainRecordNotFoundException;
use DateTimeZone;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Log row snapshots, defaults, and persistence.
 */
final class LogEntryService
{
    public function __construct(
        private readonly LogEntryRepository $repository,
        private readonly PersonService $people,
        private readonly VialService $vials,
        private readonly VialRepository $vialRows,
        private readonly AccountSyringeRepository $syringe,
        private readonly DoseCalculator $doses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function defaults(PDO $pdo, string $personId): array
    {
        $person = $this->people->requireVisible($pdo, $personId);
        $latest = $this->repository->latestForPerson($pdo, $personId);
        $vialId = $this->vialRows->currentForPerson($pdo, $personId);
        $probablyEmpty = $vialId !== null && $this->vials->probablyEmpty($pdo, $vialId);
        $loggedAt = $this->clock->now()->format('Y-m-d\TH:i');
        $payload = [
            'person_id' => $person['id'],
            'logged_at' => $loggedAt,
            'iu' => $latest['iu'] ?? null,
            'is_last_draw' => $latest['is_last_draw'] ?? false,
            'vial_id' => $vialId,
            'probably_empty' => $probablyEmpty,
            'source' => null,
        ];
        if ($latest !== null) {
            $payload['source'] = [
                'log_id' => $latest['id'],
                'logged_at' => $latest['logged_at'],
            ];
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, FieldParser $query): array
    {
        $personId = $query->requireString('person_id');
        $this->people->requireVisible($pdo, $personId);
        $before = $query->optionalString('before');
        $limit = $query->optionalPositiveInt('limit') ?? LogBookConfig::LOG_DEFAULT_LIMIT;
        if ($limit > LogBookConfig::LOG_MAX_LIMIT) {
            $limit = LogBookConfig::LOG_MAX_LIMIT;
        }

        return $this->repository->listForPerson($pdo, $personId, $before, $limit);
    }

    public function get(PDO $pdo, string $id): array
    {
        $row = $this->repository->find($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(LogBookConfig::LOG_UNKNOWN);
        }

        return $row;
    }

    public function create(PDO $pdo, FieldParser $fields): array
    {
        try {
            return $this->write($pdo, $fields, null);
        } catch (ValidationException $e) {
            $field = array_key_first($e->fields()) ?? 'unknown';
            $this->logger->info('logbook.entry.rejected', ['field' => $field]);
            throw $e;
        }
    }

    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        try {
            return $this->write($pdo, $fields, $existing);
        } catch (ValidationException $e) {
            $field = array_key_first($e->fields()) ?? 'unknown';
            $this->logger->info('logbook.entry.rejected', ['field' => $field]);
            throw $e;
        }
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->get($pdo, $id);
        $this->repository->delete($pdo, $id);
        $this->logger->info('logbook.entry.deleted', ['log_id' => $id]);
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function write(PDO $pdo, FieldParser $fields, ?array $existing): array
    {
        $personId = $fields->requireString('person_id');
        $person = $this->people->requireVisible($pdo, $personId);
        $vialId = $fields->requireString('vial_id');
        $vial = $this->vials->requireCurrent($pdo, $vialId);
        $iu = $fields->requireFloat('iu');
        try {
            $this->doses->assertIu($iu);
        } catch (ValidationException $e) {
            throw $e;
        }
        $loggedAt = $fields->requireDatetime('logged_at');
        $isLastDraw = $fields->optionalBool('is_last_draw') ?? false;
        $notes = $fields->optionalString('notes');
        $syringe = $existing !== null
            ? ['volume_ml' => $existing['syringe_volume_ml'], 'capacity_iu' => $existing['syringe_capacity_iu']]
            : $this->syringe->get($pdo);
        $doseVol = $this->doses->volumeMl($iu, $syringe['volume_ml'], $syringe['capacity_iu']);
        $doseMg = $this->doses->peptideMg(
            $iu,
            (float) $vial['peptide_mg'],
            (float) $vial['bac_water_ml'],
            $syringe['volume_ml'],
            $syringe['capacity_iu'],
        );
        $now = $this->timestamp();
        $id = $existing['id'] ?? $this->ids->uuid();
        $params = [
            ':id' => $id,
            ':person_id' => $person['id'],
            ':person_name' => $person['name'],
            ':vial_id' => $vial['id'],
            ':vial_name' => $vial['display_name'],
            ':lid_color_name' => $vial['lid_color_name'],
            ':peptide_name' => $vial['peptide_name'],
            ':bac' => $vial['bac_water_ml'],
            ':vial_mg' => $vial['peptide_mg'],
            ':conc' => $vial['concentration_mg_per_ml'],
            ':iu' => $iu,
            ':vol' => $doseVol,
            ':dose_mg' => $doseMg,
            ':s_vol' => $syringe['volume_ml'],
            ':s_cap' => $syringe['capacity_iu'],
            ':logged_at' => $loggedAt,
            ':last' => $isLastDraw ? 1 : 0,
            ':notes' => $notes,
            ':created' => $existing['created_at'] ?? $now,
            ':updated' => $now,
        ];
        if ($existing === null) {
            $this->repository->insert($pdo, $params);
        } else {
            unset($params[':created'], $params[':id'], $params[':s_vol'], $params[':s_cap']);
            $this->repository->update($pdo, $id, $params);
        }
        $this->logger->info('logbook.entry.saved', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => $iu,
            'is_last_draw' => $isLastDraw,
        ]);

        return $this->get($pdo, $id);
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}