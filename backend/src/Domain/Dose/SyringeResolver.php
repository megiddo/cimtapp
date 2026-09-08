<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;

final class SyringeResolver
{
    public function __construct(
        private readonly SyringeRepository $syringes,
        private readonly FallbackSyringe $fallback,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        $row = $this->syringes->find($pdo, $id);
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
        return $this->syringes->defaultRow($pdo) ?? $this->fallback->profile();
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackProfile(): array
    {
        return $this->fallback->profile();
    }

    /**
     * Last-used syringe still in the profile table, else the default.
     *
     * @return array<string, mixed>
     */
    public function syringeForNewUse(PDO $pdo, ?string $syringeId, ?string $profileId = null): array
    {
        if ($syringeId !== null) {
            return $this->get($pdo, $syringeId);
        }

        $lastId = $this->syringes->lastUsedId($pdo, $profileId);
        if ($lastId !== null) {
            $existing = $this->syringes->find($pdo, $lastId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->defaultSyringe($pdo);
    }
}
