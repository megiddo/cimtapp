<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class SyringeService
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, label, volume_ml, capacity_iu, is_default, quantity
             FROM syringe_profiles
             ORDER BY is_default DESC, label ASC, id ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map($this->map(...), is_array($rows) ? $rows : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->find($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::SYRINGE_UNKNOWN);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSyringe(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, label, volume_ml, capacity_iu, is_default, quantity
             FROM syringe_profiles
             WHERE is_default = 1
             LIMIT 1'
        );
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainRecordNotFoundException(DoseConfig::SYRINGE_UNKNOWN);
        }

        return $this->map($row);
    }

    /**
     * Last-used syringe still in the profile table, else the default.
     *
     * @return array<string, mixed>
     */
    public function syringeForNewUse(PDO $pdo, ?string $syringeId): array
    {
        if ($syringeId !== null) {
            return $this->get($pdo, $syringeId);
        }

        $stmt = $pdo->query(
            'SELECT syringe_id
             FROM uses
             WHERE syringe_id IS NOT NULL
             ORDER BY used_at DESC, id DESC
             LIMIT 1'
        );
        $lastId = $stmt === false ? false : $stmt->fetchColumn();
        if (is_string($lastId) && $lastId !== '') {
            $existing = $this->find($pdo, $lastId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->defaultSyringe($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $volumeMl = $fields->requirePositiveFloat('volume_ml');
        $capacityIu = $fields->requirePositiveFloat('capacity_iu');
        $label = $fields->optionalString('label') ?? DoseConfig::syringeLabel($volumeMl, $capacityIu);
        $isDefault = $fields->optionalBool('is_default') ?? false;
        $quantity = $fields->optionalPositiveInt('quantity') ?? 0;
        $id = $this->ids->uuid();

        $stmt = $pdo->prepare(
            'INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
             VALUES (:id, :label, :volume_ml, :capacity_iu, :is_default, :quantity)'
        );
        $stmt->execute([
            ':id' => $id,
            ':label' => $label,
            ':volume_ml' => $volumeMl,
            ':capacity_iu' => $capacityIu,
            ':is_default' => $isDefault ? 1 : 0,
            ':quantity' => $quantity,
        ]);

        if ($isDefault) {
            $this->setDefault($pdo, $id);
        }

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $volumeMl = $fields->has('volume_ml')
            ? $fields->requirePositiveFloat('volume_ml')
            : (float) $existing['volume_ml'];
        $capacityIu = $fields->has('capacity_iu')
            ? $fields->requirePositiveFloat('capacity_iu')
            : (float) $existing['capacity_iu'];
        $oldAuto = DoseConfig::syringeLabel((float) $existing['volume_ml'], (float) $existing['capacity_iu']);
        $label = (string) $existing['label'];
        if ($fields->has('label')) {
            $label = $fields->optionalString('label');
            if ($label === null) {
                throw new ValidationException(['label' => [DoseConfig::MUST_BE_TEXT]]);
            }
        } elseif ($label === $oldAuto) {
            $label = DoseConfig::syringeLabel($volumeMl, $capacityIu);
        }

        $stmt = $pdo->prepare(
            'UPDATE syringe_profiles
             SET label = :label, volume_ml = :volume_ml, capacity_iu = :capacity_iu
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':label' => $label,
            ':volume_ml' => $volumeMl,
            ':capacity_iu' => $capacityIu,
        ]);

        $makeDefault = $fields->optionalBool('is_default');
        if ($makeDefault === true) {
            $this->setDefault($pdo, $id);
        } elseif ($makeDefault === false && (int) $existing['is_default'] === 1) {
            throw new ValidationException(['is_default' => [DoseConfig::DEFAULT_REQUIRED]]);
        }

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->get($pdo, $id);
        $others = array_values(array_filter(
            $this->list($pdo),
            static fn (array $row): bool => (string) $row['id'] !== $id,
        ));
        if ($others === []) {
            throw new ValidationException(['id' => [DoseConfig::SYRINGE_LAST]], DoseConfig::SYRINGE_LAST);
        }

        $stmt = $pdo->prepare('DELETE FROM syringe_profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $hasDefault = false;
        foreach ($others as $row) {
            if ($row['is_default'] === true) {
                $hasDefault = true;
                break;
            }
        }
        if (!$hasDefault) {
            $this->setDefault($pdo, (string) $others[0]['id']);
        }
    }

    public function restock(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $count = $fields->requirePositiveInt('count');
        $this->setQuantity($pdo, $id, (int) $existing['quantity'] + $count);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->get($pdo, $id);
        $count = $fields->requirePositiveInt('count');
        $remaining = (int) $existing['quantity'];
        if ($count > $remaining) {
            $message = DoseConfig::syringeOverdraw($count, $remaining);
            throw new ValidationException(['count' => [$message]], $message);
        }
        $this->setQuantity($pdo, $id, $remaining - $count);

        return $this->get($pdo, $id);
    }

    public function consumeOne(PDO $pdo, string $id): void
    {
        $existing = $this->get($pdo, $id);
        $remaining = (int) $existing['quantity'];
        if ($remaining < 1) {
            throw new ValidationException(
                ['syringe_id' => [DoseConfig::SYRINGE_STOCK_EMPTY]],
                DoseConfig::SYRINGE_STOCK_EMPTY,
            );
        }
        $this->setQuantity($pdo, $id, $remaining - 1);
    }

    public function restoreOne(PDO $pdo, ?string $id): void
    {
        if ($id === null || $id === '') {
            return;
        }
        if ($this->find($pdo, $id) === null) {
            return;
        }
        $existing = $this->get($pdo, $id);
        $this->setQuantity($pdo, $id, (int) $existing['quantity'] + 1);
    }

    private function setQuantity(PDO $pdo, string $id, int $quantity): void
    {
        $stmt = $pdo->prepare('UPDATE syringe_profiles SET quantity = :quantity WHERE id = :id');
        $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }

    private function setDefault(PDO $pdo, string $id): void
    {
        $pdo->exec('UPDATE syringe_profiles SET is_default = 0');
        $stmt = $pdo->prepare('UPDATE syringe_profiles SET is_default = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, label, volume_ml, capacity_iu, is_default, quantity
             FROM syringe_profiles
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'label' => (string) $row['label'],
            'volume_ml' => (float) $row['volume_ml'],
            'capacity_iu' => (float) $row['capacity_iu'],
            'is_default' => (int) $row['is_default'] === 1,
            'quantity' => (int) $row['quantity'],
        ];
    }
}
