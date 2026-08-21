<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface SessionRepository
{
    public function insert(Session $session): void;

    public function findById(string $id): ?Session;

    public function delete(string $id): void;

    public function updateExpiry(string $id, string $expiresAt): void;
}
