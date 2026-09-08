<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Crypto\Crypto;
use DateTimeZone;
use PDOException;

final class UserFactory
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserProvisioner $provisioner,
        private readonly Crypto $crypto,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function create(string $email, ?string $passwordHash, ?string $googleSub): User
    {
        $id = $this->ids->uuid();
        $dek = $this->crypto->mintDek();
        $wrapped = $this->crypto->wrapDek($dek);
        $now = $this->timestamp();
        $user = new User(
            $id,
            $email,
            $passwordHash,
            $googleSub,
            $wrapped->ciphertext(),
            $wrapped->nonce(),
            $now,
            null,
        );

        try {
            $this->users->transactional(function () use ($user, $id, $dek, $email, $passwordHash, $googleSub, $now): void {
                $this->users->insert($user);
                $this->provisioner->provision($id, $dek, $email, $passwordHash, $googleSub, $now);
            });
        } catch (PDOException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ValidationException(['email' => [AuthConfig::EMAIL_TAKEN]]);
            }
            throw $e;
        }

        return $user;
    }

    public function mustFind(string $id): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }

        return $user;
    }

    public function touchLogin(User $user): User
    {
        $this->users->updateLastLogin($user->id, $this->timestamp());

        return $this->mustFind($user->id);
    }

    public function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function isUniqueViolation(PDOException $e): bool
    {
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE');
    }
}
