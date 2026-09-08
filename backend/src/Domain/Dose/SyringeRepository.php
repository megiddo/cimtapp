<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class SyringeRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, ?StockList $query = null): array
    {
        $query ??= StockList::openOnly();
        $sql = $query->includesArchived()
            ? 'SELECT id, label, volume_ml, capacity_iu, is_default, quantity, archived_at
               FROM syringe_profiles
               ORDER BY (archived_at IS NULL) DESC, is_default DESC, label ASC, id ASC'
            : 'SELECT id, label, volume_ml, capacity_iu, is_default, quantity, archived_at
               FROM syringe_profiles
               WHERE archived_at IS NULL
               ORDER BY is_default DESC, label ASC, id ASC';
        $stmt = $pdo->query($sql);
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, label, volume_ml, capacity_iu, is_default, quantity, archived_at
             FROM syringe_profiles
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultRow(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT id, label, volume_ml, capacity_iu, is_default, quantity, archived_at
             FROM syringe_profiles
             WHERE archived_at IS NULL
             ORDER BY is_default DESC, label ASC, id ASC
             LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
             VALUES (:id, :label, :volume_ml, :capacity_iu, :is_default, :quantity)'
        );
        $stmt->execute($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(PDO $pdo, array $values): void
    {
        $stmt = $pdo->prepare(
            'UPDATE syringe_profiles
             SET label = :label, volume_ml = :volume_ml, capacity_iu = :capacity_iu
             WHERE id = :id'
        );
        $stmt->execute($values);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM syringe_profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function setQuantity(PDO $pdo, string $id, int $quantity): void
    {
        $stmt = $pdo->prepare('UPDATE syringe_profiles SET quantity = :quantity WHERE id = :id');
        $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }

    public function setDefault(PDO $pdo, string $id): void
    {
        $pdo->exec('UPDATE syringe_profiles SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE syringe_profiles SET is_default = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Last-used syringe id still on a profile, else null.
     */
    public function lastUsedId(PDO $pdo, ?string $profileId = null): ?string
    {
        if ($profileId !== null) {
            $stmt = $pdo->prepare(
                'SELECT syringe_id
                 FROM uses
                 WHERE syringe_id IS NOT NULL AND profile_id = :profile_id
                 ORDER BY used_at DESC, id DESC
                 LIMIT 1'
            );
            $stmt->execute([':profile_id' => $profileId]);
            $lastId = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->query(
                'SELECT syringe_id
                 FROM uses
                 WHERE syringe_id IS NOT NULL
                 ORDER BY used_at DESC, id DESC
                 LIMIT 1'
            );
            $lastId = $stmt === false ? false : $stmt->fetchColumn();
        }

        return is_string($lastId) && $lastId !== '' ? $lastId : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'label' => (string) $row['label'],
            'volume_ml' => (float) $row['volume_ml'],
            'capacity_iu' => (float) $row['capacity_iu'],
            'is_default' => (int) $row['is_default'] === 1,
            'quantity' => (int) $row['quantity'],
            'archived_at' => !isset($row['archived_at']) || $row['archived_at'] === null || $row['archived_at'] === ''
                ? null
                : (string) $row['archived_at'],
        ];
    }
}
