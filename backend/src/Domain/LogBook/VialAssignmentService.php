<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\Clock;
use DateTimeZone;
use PDO;

/**
 * Mutates vial_people links and derived display names when people change.
 */
final class VialAssignmentService
{
    public function __construct(
        private readonly VialRepository $vials,
        private readonly VialPeopleRepository $people,
        private readonly VialNamer $namer,
        private readonly AccountSyringeRepository $accountSyringe,
        private readonly Clock $clock,
    ) {
    }

    public function unassignPerson(PDO $pdo, string $personId): void
    {
        foreach ($this->people->currentVialIdsForPerson($pdo, $personId) as $vialId) {
            $this->people->removePerson($pdo, $vialId, $personId);
            $this->refreshDisplayName($pdo, $vialId);
            if ($this->people->listForVial($pdo, $vialId) === []) {
                $this->vials->supersede($pdo, $vialId, $this->timestamp());
            }
        }
    }

    public function refreshDisplayNamesForPerson(PDO $pdo, string $personId, string $newName): void
    {
        unset($newName);
        foreach ($this->people->currentVialIdsForPerson($pdo, $personId) as $vialId) {
            $this->refreshDisplayName($pdo, $vialId);
        }
    }

    public function refreshDisplayName(PDO $pdo, string $vialId): void
    {
        $row = $this->vials->find($pdo, $vialId);
        if ($row === null) {
            return;
        }
        $links = $this->people->listForVial($pdo, $vialId);
        if ($links === []) {
            return;
        }
        $names = array_map(static fn (array $r): string => $r['name'], $links);
        $syringe = $this->accountSyringe->get($pdo);
        $this->vials->updateDisplayName(
            $pdo,
            $vialId,
            $this->namer->build(
                (string) $row['peptide_name'],
                $names,
                (float) $row['concentration_mg_per_ml'],
                $syringe['volume_ml'],
                $syringe['capacity_iu'],
            ),
        );
    }

    /**
     * @return list<string> person ids unassigned from prior current vials
     */
    public function assignAsCurrent(PDO $pdo, string $vialId, array $personIds): array
    {
        $unassigned = [];
        foreach ($personIds as $personId) {
            $old = $this->vials->currentForPerson($pdo, $personId);
            if ($old !== null && $old !== $vialId) {
                $this->people->removePerson($pdo, $old, $personId);
                $this->refreshDisplayName($pdo, $old);
                if ($this->people->listForVial($pdo, $old) === []) {
                    $this->vials->supersede($pdo, $old, $this->timestamp());
                }
            }
        }
        $this->people->replaceForVial($pdo, $vialId, $personIds);
        $this->refreshDisplayName($pdo, $vialId);

        return $unassigned;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}