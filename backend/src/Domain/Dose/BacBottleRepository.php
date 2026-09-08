<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class BacBottleRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listRows(PDO $pdo, StockList $query): array
    {
        $sql = $query->includesArchived()
            ? 'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at, archived_at
               FROM bac_bottles
               ORDER BY (archived_at IS NULL) DESC, opened_at DESC, id DESC'
            : 'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at, archived_at
               FROM bac_bottles
               WHERE archived_at IS NULL
               ORDER BY opened_at DESC, id DESC';
        $stmt = $pdo->query($sql);
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at, archived_at
             FROM bac_bottles
             WHERE remaining_ml > 0 AND archived_at IS NULL
             ORDER BY opened_at DESC, id DESC
             LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRow(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, volume_ml, remaining_ml, opened_at, notes, created_at, archived_at
             FROM bac_bottles
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO bac_bottles (id, volume_ml, remaining_ml, opened_at, notes, created_at)
             VALUES (:id, :volume_ml, :remaining_ml, :opened_at, :notes, :created_at)'
        );
        $stmt->execute($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateNotes(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'UPDATE bac_bottles SET opened_at = :opened_at, notes = :notes WHERE id = :id'
        );
        $stmt->execute($values);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM bac_bottles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function setRemaining(PDO $pdo, string $id, float $remainingMl): void
    {
        $stmt = $pdo->prepare('UPDATE bac_bottles SET remaining_ml = :remaining_ml WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':remaining_ml' => $remainingMl,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function map(array $row, ?string $currentId): array
    {
        $id = (string) $row['id'];

        return [
            'id' => $id,
            'volume_ml' => (float) $row['volume_ml'],
            'remaining_ml' => (float) $row['remaining_ml'],
            'opened_at' => (string) $row['opened_at'],
            'notes' => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at' => (string) $row['created_at'],
            'archived_at' => !isset($row['archived_at']) || $row['archived_at'] === null || $row['archived_at'] === ''
                ? null
                : (string) $row['archived_at'],
            'is_current' => $currentId !== null && $id === $currentId,
        ];
    }

    public function currentId(PDO $pdo): ?string
    {
        $row = $this->currentRow($pdo);

        return $row === null ? null : (string) $row['id'];
    }
}
