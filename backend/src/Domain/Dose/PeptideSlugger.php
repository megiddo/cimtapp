<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\IdGenerator;

final class PeptideSlugger
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    /**
     * @param list<array{id: string, slug: string, name: string, sort_order: int}> $taken
     */
    public function uniqueSlug(array $taken, string $name): string
    {
        $base = $this->slug($name);
        $index = [];
        foreach ($taken as $row) {
            $index[(string) $row['slug']] = true;
            $index[(string) $row['id']] = true;
        }
        if (!isset($index[$base])) {
            return $base;
        }

        return $base . '-' . substr($this->ids->uuid(), 0, 8);
    }

    public function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'peptide' : $slug;
    }
}
