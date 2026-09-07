<?php

declare(strict_types=1);

namespace App\Domain\Dose;

/**
 * Query object for inventory lists: open-only (sheet) vs open-then-archived.
 */
final class StockList
{
    private function __construct(private readonly bool $includeArchived)
    {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fromQuery(array $params): self
    {
        return ($params['view'] ?? null) === 'all' ? self::allGrouped() : self::openOnly();
    }

    public static function openOnly(): self
    {
        return new self(false);
    }

    public static function allGrouped(): self
    {
        return new self(true);
    }

    public function includesArchived(): bool
    {
        return $this->includeArchived;
    }
}
