<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\ValidationException;

final class ArchivePolicy
{
    public function __construct(private readonly DoseCalculator $doses)
    {
    }

    public function apply(StockItem $item, Clock $clock): void
    {
        if ($item->isArchived()) {
            throw new ValidationException(['id' => [DoseConfig::ALREADY_ARCHIVED]], DoseConfig::ALREADY_ARCHIVED);
        }
        if (!$this->doses->isDepleted($item->remaining())) {
            throw new ValidationException(['id' => [DoseConfig::ARCHIVE_NOT_EMPTY]], DoseConfig::ARCHIVE_NOT_EMPTY);
        }

        $item->archive($clock);
    }
}
