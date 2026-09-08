<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;
use PDO;

final class UseQueryService
{
    public function __construct(private readonly UseRepository $uses)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo, FieldParser $query): array
    {
        return $this->uses->list(
            $pdo,
            $this->limit($query),
            $this->before($query),
            $query->optionalString('profile_id'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->uses->get($pdo, $id);
    }

    private function before(FieldParser $query): ?string
    {
        try {
            return $query->optionalDatetime('before');
        } catch (ValidationException) {
            throw new ValidationException(['before' => [DoseConfig::BEFORE_INVALID]]);
        }
    }

    private function limit(FieldParser $query): int
    {
        if (!$query->has('limit')) {
            return DoseConfig::USES_DEFAULT_LIMIT;
        }

        $limit = (int) $query->requireFloat('limit');
        if ($limit < 1) {
            throw new ValidationException(['limit' => [DoseConfig::LIMIT_INVALID]]);
        }
        if ($limit > DoseConfig::USES_MAX_LIMIT) {
            return DoseConfig::USES_MAX_LIMIT;
        }

        return $limit;
    }
}
