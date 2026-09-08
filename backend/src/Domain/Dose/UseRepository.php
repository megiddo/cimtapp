<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class UseRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, int $limit, ?string $before, ?string $profileId): array
    {
        $sql = 'SELECT uses.*, compounds.peptide_type_name, compounds.name AS compound_name,
                       profiles.name AS profile_name
                FROM uses
                JOIN compounds ON compounds.id = uses.compound_id
                LEFT JOIN profiles ON profiles.id = uses.profile_id';
        $where = [];
        if ($before !== null) {
            $where[] = 'uses.used_at < :before';
        }
        if ($profileId !== null) {
            $where[] = 'uses.profile_id = :profile_id';
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY uses.used_at DESC, uses.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        if ($before !== null) {
            $stmt->bindValue(':before', $before);
        }
        if ($profileId !== null) {
            $stmt->bindValue(':profile_id', $profileId);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT uses.*, compounds.peptide_type_name, compounds.name AS compound_name,
                    profiles.name AS profile_name
             FROM uses
             JOIN compounds ON compounds.id = uses.compound_id
             LEFT JOIN profiles ON profiles.id = uses.profile_id
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
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO uses (
                id, compound_id, profile_id, iu, syringe_id, syringe_label, syringe_volume_ml, syringe_capacity_iu,
                volume_ml, peptide_mg, used_at, notes, created_at, updated_at
             ) VALUES (
                :id, :compound_id, :profile_id, :iu, :syringe_id, :syringe_label, :syringe_volume_ml, :syringe_capacity_iu,
                :volume_ml, :peptide_mg, :used_at, :notes, :created_at, :updated_at
             )'
        );
        $stmt->execute($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'UPDATE uses SET
                profile_id = :profile_id,
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
        $stmt->execute($values);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM uses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'compound_id' => (string) $row['compound_id'],
            'profile_id' => $row['profile_id'] === null ? null : (string) $row['profile_id'],
            'profile_name' => $row['profile_name'] === null ? null : (string) $row['profile_name'],
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
}
