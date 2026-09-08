<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\ValidationException;
use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class BacBottleLifecycle
{
    public function __construct(
        private readonly BacBottleRepository $bottles,
        private readonly BacLedger $ledger,
        private readonly ArchivePolicy $archivePolicy,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function burn(PDO $pdo, string $id, FieldParser $fields): array
    {
        $row = $this->bottles->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        $this->ledger->debitRow($pdo, $row, $fields->requirePositiveFloat('ml'), 'ml');

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(PDO $pdo, string $id): array
    {
        $existing = $this->get($pdo, $id);
        $this->archivePolicy->apply(new BacStock($pdo, $existing), $this->clock);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->bottles->findRow($pdo, $id);
        if ($row === null) {
            throw new DomainRecordNotFoundException(DoseConfig::BAC_UNKNOWN);
        }

        return $this->bottles->map($row, $this->bottles->currentId($pdo));
    }
}
