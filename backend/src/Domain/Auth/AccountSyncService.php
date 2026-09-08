<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Crypto\Crypto;
use PDO;

final class AccountSyncService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserProvisioner $provisioner,
        private readonly Crypto $crypto,
        private readonly UserStorePort $store,
        private readonly UserFactory $factory,
        private readonly PasswordHasher $passwords,
    ) {
    }

    public function setPassword(User $user, string $password, PDO $userPdo): User
    {
        if (strlen($password) < AuthConfig::PASSWORD_MIN_LENGTH) {
            throw new ValidationException(['password' => [AuthConfig::PASSWORD_TOO_SHORT]]);
        }

        $hash = $this->passwords->hash($password);
        $this->users->setPasswordHash($user->id, $hash);
        $this->provisioner->syncAccount(
            $userPdo,
            $user->id,
            $user->email,
            $hash,
            $user->googleSub,
            $this->factory->timestamp(),
        );

        return $this->factory->mustFind($user->id);
    }

    public function attachGoogle(User $user, string $email, string $googleSub): void
    {
        $this->users->setGoogleSub($user->id, $googleSub);
        $dek = $this->crypto->unwrapDek($user->dekNonce, $user->encryptedDek);
        $now = $this->factory->timestamp();
        $this->store->withUnlocked($user->id, $dek, function (PDO $pdo) use ($user, $email, $googleSub, $now): void {
            $this->provisioner->syncAccount(
                $pdo,
                $user->id,
                $email,
                $user->passwordHash,
                $googleSub,
                $now,
            );
        });
    }
}
