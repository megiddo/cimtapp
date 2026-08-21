<?php

declare(strict_types=1);

namespace App\Domain\Dose;

interface PeptideCatalog
{
    /**
     * Active peptides, sort_order ASC.
     *
     * @return list<array{id: string, slug: string, name: string, sort_order: int}>
     */
    public function listActive(): array;

    /**
     * @return array{id: string, slug: string, name: string, sort_order: int}|null
     */
    public function findActiveById(string $id): ?array;
}
