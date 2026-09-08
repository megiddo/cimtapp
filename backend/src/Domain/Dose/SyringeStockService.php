<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;
use PDO;

final class SyringeStockService
{
    public function __construct(
        private readonly SyringeRepository $syringes,
        private readonly SyringeResolver $resolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function restock(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->resolver->get($pdo, $id);
        $count = $fields->requirePositiveInt('count');
        $this->syringes->setQuantity($pdo, $id, (int) $existing['quantity'] + $count);

        return $this->resolver->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        $existing = $this->resolver->get($pdo, $id);
        $count = $fields->requirePositiveInt('count');
        $remaining = (int) $existing['quantity'];
        if ($count > $remaining) {
            $message = DoseConfig::syringeOverdraw($count, $remaining);
            throw new ValidationException(['count' => [$message]], $message);
        }
        $this->syringes->setQuantity($pdo, $id, $remaining - $count);

        return $this->resolver->get($pdo, $id);
    }

    public function consumeOne(PDO $pdo, string $id): void
    {
        $existing = $this->resolver->get($pdo, $id);
        $remaining = (int) $existing['quantity'];
        if ($remaining < 1) {
            throw new ValidationException(
                ['syringe_id' => [DoseConfig::SYRINGE_STOCK_EMPTY]],
                DoseConfig::SYRINGE_STOCK_EMPTY,
            );
        }
        $this->syringes->setQuantity($pdo, $id, $remaining - 1);
    }

    public function restoreOne(PDO $pdo, ?string $id): void
    {
        if ($id === null || $id === '') {
            return;
        }
        if ($this->syringes->find($pdo, $id) === null) {
            return;
        }
        $existing = $this->resolver->get($pdo, $id);
        $this->syringes->setQuantity($pdo, $id, (int) $existing['quantity'] + 1);
    }
}
