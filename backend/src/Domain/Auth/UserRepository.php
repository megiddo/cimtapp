<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Crypto\WrappedDek;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByGoogleSub(string $sub): ?User;

    /**
     * @return list<User>
     */
    public function listAll(): array;

    public function insert(User $user): void;

    /**
     * @param callable(): void $callback
     */
    public function transactional(callable $callback): void;

    public function setGoogleSub(string $userId, string $googleSub): void;

    public function setPasswordHash(string $userId, string $passwordHash): void;

    public function updateLastLogin(string $userId, string $lastLoginAt): void;

    public function updateWrappedDek(string $userId, WrappedDek $wrapped): void;
}
