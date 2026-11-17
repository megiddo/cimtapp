<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class VialRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listCurrent(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, peptide_id, peptide_name, peptide_mg, bac_water_ml, concentration_mg_per_ml,
                    display_name, lid_color_id, lid_color_name, usage_offset_mg, compounded_at, created_at
             FROM vials WHERE superseded_at IS NULL
             ORDER BY compounded_at DESC, id DESC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->mapRow(...), is_array($rows) ? $rows : []));
    }

    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, peptide_id, peptide_name, peptide_mg, bac_water_ml, concentration_mg_per_ml,
                    display_name, lid_color_id, lid_color_name, usage_offset_mg, compounded_at,
                    superseded_at, created_at FROM vials WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapRow($row, true) : null;
    }

    public function isCurrent(PDO $pdo, string $id): bool
    {
        $stmt = $pdo->prepare('SELECT superseded_at FROM vials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $value = $stmt->fetchColumn();

        return $value === false ? false : $value === null;
    }

    /**
     * @param list<string> $personIds
     */
    public function insert(
        PDO $pdo,
        string $id,
        string $peptideId,
        string $peptideName,
        float $peptideMg,
        float $bacWaterMl,
        float $concentration,
        string $displayName,
        ?string $lidColorId,
        string $lidColorName,
        string $compoundedAt,
        string $createdAt,
    ): void {
        $pdo->prepare(
            'INSERT INTO vials (
                id, peptide_id, peptide_name, peptide_mg, bac_water_ml, concentration_mg_per_ml,
                display_name, lid_color_id, lid_color_name, usage_offset_mg, compounded_at, superseded_at, created_at
            ) VALUES (
                :id, :pep, :pname, :mg, :bac, :conc, :dname, :lid, :lname, 0, :cat, NULL, :crt
            )'
        )->execute([
            ':id' => $id,
            ':pep' => $peptideId,
            ':pname' => $peptideName,
            ':mg' => $peptideMg,
            ':bac' => $bacWaterMl,
            ':conc' => $concentration,
            ':dname' => $displayName,
            ':lid' => $lidColorId,
            ':lname' => $lidColorName,
            ':cat' => $compoundedAt,
            ':crt' => $createdAt,
        ]);
    }

    public function updateDisplayName(PDO $pdo, string $id, string $name): void
    {
        $pdo->prepare('UPDATE vials SET display_name = :name WHERE id = :id')->execute([':id' => $id, ':name' => $name]);
    }

    public function supersede(PDO $pdo, string $id, string $when): void
    {
        $pdo->prepare(
            'UPDATE vials SET superseded_at = COALESCE(superseded_at, :when) WHERE id = :id'
        )->execute([':id' => $id, ':when' => $when]);
    }

    public function currentForPerson(PDO $pdo, string $personId): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT vp.vial_id FROM vial_people vp
             INNER JOIN vials v ON v.id = vp.vial_id AND v.superseded_at IS NULL
             WHERE vp.person_id = :person LIMIT 1'
        );
        $stmt->execute([':person' => $personId]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function sumDoseMg(PDO $pdo, string $vialId): float
    {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(dose_mg), 0) FROM log_entries WHERE vial_id = :id');
        $stmt->execute([':id' => $vialId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * @return array<string, float>
     */
    public function drawnMgByPerson(PDO $pdo, string $vialId): array
    {
        $stmt = $pdo->prepare(
            'SELECT person_id, COALESCE(SUM(dose_mg), 0) AS total
             FROM log_entries WHERE vial_id = :id AND person_id IS NOT NULL GROUP BY person_id'
        );
        $stmt->execute([':id' => $vialId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['person_id'] !== null) {
                $out[(string) $row['person_id']] = LogBookFormatter::roundMg((float) $row['total']);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row, bool $includeSuperseded = false): array
    {
        $mapped = [
            'id' => (string) $row['id'],
            'peptide_id' => (string) $row['peptide_id'],
            'peptide_name' => (string) $row['peptide_name'],
            'peptide_mg' => (float) $row['peptide_mg'],
            'bac_water_ml' => (float) $row['bac_water_ml'],
            'concentration_mg_per_ml' => (float) $row['concentration_mg_per_ml'],
            'display_name' => (string) $row['display_name'],
            'lid_color_id' => $row['lid_color_id'] !== null ? (string) $row['lid_color_id'] : null,
            'lid_color_name' => (string) ($row['lid_color_name'] ?? ''),
            'usage_offset_mg' => (float) ($row['usage_offset_mg'] ?? 0),
            'compounded_at' => (string) $row['compounded_at'],
            'created_at' => (string) $row['created_at'],
        ];
        if ($includeSuperseded) {
            $mapped['superseded_at'] = $row['superseded_at'] !== null ? (string) $row['superseded_at'] : null;
        }

        return $mapped;
    }
}