<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class LogEntryRepository
{
    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM log_entries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPerson(PDO $pdo, string $personId, ?string $before, int $limit): array
    {
        $sql = 'SELECT * FROM log_entries WHERE person_id = :person';
        $params = [':person' => $personId];
        if ($before !== null) {
            $sql .= ' AND logged_at < :before';
            $params[':before'] = $before;
        }
        $sql .= ' ORDER BY logged_at DESC, id DESC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map($this->map(...), $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    public function latestForPerson(PDO $pdo, string $personId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM log_entries WHERE person_id = :person ORDER BY logged_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':person' => $personId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $pdo->prepare(
            'INSERT INTO log_entries (
                id, person_id, person_name, vial_id, vial_name, lid_color_name,
                peptide_name, bac_water_ml, peptide_mg_in_vial, concentration_mg_per_ml,
                iu, dose_volume_ml, dose_mg, syringe_volume_ml, syringe_capacity_iu,
                logged_at, is_last_draw, notes, created_at, updated_at
            ) VALUES (
                :id, :person_id, :person_name, :vial_id, :vial_name, :lid_color_name,
                :peptide_name, :bac, :vial_mg, :conc, :iu, :vol, :dose_mg, :s_vol, :s_cap,
                :logged_at, :last, :notes, :created, :updated
            )'
        )->execute($values);
    }

    public function update(PDO $pdo, string $id, array $values): void
    {
        $pdo->prepare(
            'UPDATE log_entries SET
                person_id = :person_id, person_name = :person_name, vial_id = :vial_id,
                vial_name = :vial_name, lid_color_name = :lid_color_name, peptide_name = :peptide_name,
                bac_water_ml = :bac, peptide_mg_in_vial = :vial_mg, concentration_mg_per_ml = :conc,
                iu = :iu, dose_volume_ml = :vol, dose_mg = :dose_mg,
                logged_at = :logged_at, is_last_draw = :last, notes = :notes, updated_at = :updated
             WHERE id = :id'
        )->execute($values + [':id' => $id]);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $pdo->prepare('DELETE FROM log_entries WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'person_id' => $row['person_id'] !== null ? (string) $row['person_id'] : null,
            'person_name' => (string) $row['person_name'],
            'vial_id' => $row['vial_id'] !== null ? (string) $row['vial_id'] : null,
            'vial_name' => (string) $row['vial_name'],
            'lid_color_name' => (string) $row['lid_color_name'],
            'peptide_name' => (string) $row['peptide_name'],
            'bac_water_ml' => (float) $row['bac_water_ml'],
            'peptide_mg_in_vial' => (float) $row['peptide_mg_in_vial'],
            'concentration_mg_per_ml' => (float) $row['concentration_mg_per_ml'],
            'iu' => (float) $row['iu'],
            'dose_volume_ml' => (float) $row['dose_volume_ml'],
            'dose_mg' => (float) $row['dose_mg'],
            'syringe_volume_ml' => (float) $row['syringe_volume_ml'],
            'syringe_capacity_iu' => (float) $row['syringe_capacity_iu'],
            'logged_at' => (string) $row['logged_at'],
            'is_last_draw' => (int) $row['is_last_draw'] === 1,
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}