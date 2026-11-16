<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class VialPeopleRepository
{
    /**
     * @return list<array{person_id: string, name: string, sort_order: int}>
     */
    public function listForVial(PDO $pdo, string $vialId): array
    {
        $stmt = $pdo->prepare(
            'SELECT vp.person_id, p.name, vp.sort_order
             FROM vial_people vp
             INNER JOIN people p ON p.id = vp.person_id
             WHERE vp.vial_id = :vial
             ORDER BY vp.sort_order ASC, vp.person_id ASC'
        );
        $stmt->execute([':vial' => $vialId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'person_id' => (string) $row['person_id'],
                'name' => (string) $row['name'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $out;
    }

    public function replaceForVial(PDO $pdo, string $vialId, array $personIds): void
    {
        $pdo->prepare('DELETE FROM vial_people WHERE vial_id = :vial')->execute([':vial' => $vialId]);
        $insert = $pdo->prepare(
            'INSERT INTO vial_people (vial_id, person_id, sort_order) VALUES (:vial, :person, :ord)'
        );
        foreach (array_values($personIds) as $i => $personId) {
            $insert->execute([':vial' => $vialId, ':person' => $personId, ':ord' => $i + 1]);
        }
    }

    public function removePerson(PDO $pdo, string $vialId, string $personId): void
    {
        $pdo->prepare(
            'DELETE FROM vial_people WHERE vial_id = :vial AND person_id = :person'
        )->execute([':vial' => $vialId, ':person' => $personId]);
    }

    /**
     * @return list<string> vial ids that still have this person
     */
    public function currentVialIdsForPerson(PDO $pdo, string $personId): array
    {
        $stmt = $pdo->prepare(
            'SELECT vp.vial_id FROM vial_people vp
             INNER JOIN vials v ON v.id = vp.vial_id AND v.superseded_at IS NULL
             WHERE vp.person_id = :person'
        );
        $stmt->execute([':person' => $personId]);

        return array_map(static fn ($id): string => (string) $id, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}