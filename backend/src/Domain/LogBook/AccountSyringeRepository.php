<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use PDO;

final class AccountSyringeRepository
{
    public function get(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT volume_ml, capacity_iu FROM account_syringe WHERE id = 1');
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('Account syringe missing.');
        }

        return [
            'volume_ml' => (float) $row['volume_ml'],
            'capacity_iu' => (float) $row['capacity_iu'],
        ];
    }

    public function replace(PDO $pdo, float $volumeMl, float $capacityIu): void
    {
        $pdo->prepare(
            'INSERT INTO account_syringe (id, volume_ml, capacity_iu) VALUES (1, :vol, :cap)
             ON CONFLICT(id) DO UPDATE SET volume_ml = excluded.volume_ml, capacity_iu = excluded.capacity_iu'
        )->execute([':vol' => $volumeMl, ':cap' => $capacityIu]);
    }
}