<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use DateTimeZone;
use PDO;

final class CompoundStock implements StockItem
{
    /**
     * @param array<string, mixed> $row presented compound
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $row,
    ) {
    }

    public function id(): string
    {
        return (string) $this->row['id'];
    }

    public function kind(): string
    {
        return 'compound';
    }

    public function remaining(): float
    {
        return (float) $this->row['remaining_mg'];
    }

    public function isArchived(): bool
    {
        return $this->row['archived_at'] !== null;
    }

    public function archive(Clock $clock): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE compounds SET archived_at = :archived_at, is_open = 0 WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $this->id(),
            ':archived_at' => $clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
