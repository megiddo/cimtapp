<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use PDO;

final class SyringeService
{
    private readonly SyringeRepository $repository;
    private readonly SyringeResolver $resolver;
    private readonly SyringeStockService $stock;

    public function __construct(private readonly IdGenerator $ids)
    {
        $this->repository = new SyringeRepository();
        $this->resolver = new SyringeResolver($this->repository, new FallbackSyringe());
        $this->stock = new SyringeStockService($this->repository, $this->resolver);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, ?StockList $query = null): array
    {
        return $this->repository->list($pdo, $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->resolver->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSyringe(PDO $pdo): array
    {
        return $this->resolver->defaultSyringe($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackProfile(): array
    {
        return $this->resolver->fallbackProfile();
    }

    /**
     * @return array<string, mixed>
     */
    public function syringeForNewUse(PDO $pdo, ?string $syringeId, ?string $profileId = null): array
    {
        return $this->resolver->syringeForNewUse($pdo, $syringeId, $profileId);
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

        $this->repository->insert($pdo, [
            ':id' => $id,
            ':label' => $label,
            ':volume_ml' => $volumeMl,
            ':capacity_iu' => $capacityIu,
            ':is_default' => $isDefault ? 1 : 0,
            ':quantity' => $quantity,
        ]);

        if ($isDefault) {
            $this->repository->setDefault($pdo, $id);
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

        $this->repository->update($pdo, [
            ':id' => $id,
            ':label' => $label,
            ':volume_ml' => $volumeMl,
            ':capacity_iu' => $capacityIu,
        ]);

        $makeDefault = $fields->optionalBool('is_default');
        if ($makeDefault === true) {
            $this->repository->setDefault($pdo, $id);
        } elseif ($makeDefault === false && (int) $existing['is_default'] === 1) {
            throw new ValidationException(['is_default' => [DoseConfig::DEFAULT_REQUIRED]]);
        }

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $this->get($pdo, $id);
        $others = array_values(array_filter(
            $this->list($pdo, StockList::allGrouped()),
            static fn (array $row): bool => (string) $row['id'] !== $id,
        ));
        if ($others === []) {
            throw new ValidationException(['id' => [DoseConfig::SYRINGE_LAST]], DoseConfig::SYRINGE_LAST);
        }

        $this->repository->delete($pdo, $id);

        $hasDefault = false;
        foreach ($others as $row) {
            if ($row['is_default'] === true) {
                $hasDefault = true;
                break;
            }
        }
        if (!$hasDefault) {
            $this->repository->setDefault($pdo, (string) $others[0]['id']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function restock(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->stock->restock($pdo, $id, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        return $this->stock->burn($pdo, $id, $fields);
    }

    public function consumeOne(PDO $pdo, string $id): void
    {
        $this->stock->consumeOne($pdo, $id);
    }

    public function restoreOne(PDO $pdo, ?string $id): void
    {
        $this->stock->restoreOne($pdo, $id);
    }
}
