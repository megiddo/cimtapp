<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use PDO;

final class AccountSyringeService
{
    public function __construct(private readonly AccountSyringeRepository $repository)
    {
    }

    public function get(PDO $pdo): array
    {
        return $this->repository->get($pdo);
    }

    public function put(PDO $pdo, FieldParser $fields): array
    {
        $volume = $fields->requireFloat('volume_ml');
        $capacity = $fields->requireFloat('capacity_iu');
        if ($volume <= 0.0) {
            throw new ValidationException(['volume_ml' => [DoseConfig::MUST_BE_POSITIVE]]);
        }
        if ($capacity <= 0.0) {
            throw new ValidationException(['capacity_iu' => [DoseConfig::MUST_BE_POSITIVE]]);
        }
        $this->repository->replace($pdo, $volume, $capacity);

        return $this->get($pdo);
    }
}