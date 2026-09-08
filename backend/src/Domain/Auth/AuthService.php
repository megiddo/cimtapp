<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Crypto\Crypto;
use PDO;

final class AuthService
{
    private readonly PasswordAuthService $passwords;
    private readonly GoogleAuthService $google;
    private readonly AccountSyncService $sync;
    private readonly MeIdentityReader $me;

    public function __construct(
        UserRepository $users,
        UserProvisioner $provisioner,
        Crypto $crypto,
        PasswordHasher $hasher,
        EmailNormalizer $emails,
        IdGenerator $ids,
        Clock $clock,
        UserStorePort $store,
    ) {
        $factory = new UserFactory($users, $provisioner, $crypto, $ids, $clock);
        $this->sync = new AccountSyncService($users, $provisioner, $crypto, $store, $factory, $hasher);
        $this->passwords = new PasswordAuthService($users, $hasher, $emails, $factory);
        $this->google = new GoogleAuthService($users, $emails, $factory, $this->sync);
        $this->me = new MeIdentityReader();
    }

    public function register(string $email, string $password): User
    {
        return $this->passwords->register($email, $password);
    }

    public function login(string $email, string $password): User
    {
        return $this->passwords->login($email, $password);
    }

    public function loginWithGoogle(GoogleUserInfo $info): User
    {
        return $this->google->login($info);
    }

    public function setPassword(User $user, string $password, PDO $userPdo): User
    {
        return $this->sync->setPassword($user, $password, $userPdo);
    }

    /**
     * @return array{email: string, has_password: bool, has_google: bool, remainder: null, open_vials: list<empty>}
     */
    public function meFromUserDb(PDO $pdo): array
    {
        return $this->me->fromUserDb($pdo);
    }
}
