<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Crypto\Crypto;
use DateTimeZone;
use PDO;
use PDOException;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserProvisioner $provisioner,
        private readonly Crypto $crypto,
        private readonly PasswordHasher $passwords,
        private readonly EmailNormalizer $emails,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly UserStorePort $store,
    ) {
    }

    public function register(string $email, string $password): User
    {
        $email = $this->emails->normalize($email);
        $this->assertCredentials($email, $password);

        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException(['email' => [AuthConfig::EMAIL_TAKEN]]);
        }

        $user = $this->createUser($email, $this->passwords->hash($password), null);
        $this->touchLogin($user);

        return $this->mustFind($user->id);
    }

    public function login(string $email, string $password): User
    {
        $email = $this->emails->normalize($email);
        $user = $this->emails->isValid($email) ? $this->users->findByEmail($email) : null;
        $storedHash = ($user !== null && $user->hasPassword()) ? $user->passwordHash : null;
        $verified = $this->passwords->verify(
            $password,
            $storedHash ?? $this->passwords->dummyHash()
        );

        if ($user === null || $storedHash === null || $verified !== true) {
            throw new AuthenticationException(AuthConfig::GENERIC_LOGIN_ERROR);
        }

        $this->touchLogin($user);

        return $this->mustFind($user->id);
    }

    public function loginWithGoogle(GoogleUserInfo $info): User
    {
        if ($info->emailVerified !== true) {
            throw new UnverifiedGoogleEmailException(AuthConfig::GOOGLE_FAILED);
        }

        $email = $this->emails->normalize($info->email);
        if ($info->sub === '' || !$this->emails->isValid($email)) {
            throw new GoogleOAuthException(AuthConfig::GOOGLE_FAILED);
        }

        $bySub = $this->users->findByGoogleSub($info->sub);
        $byEmail = $this->users->findByEmail($email);

        if ($bySub !== null && $byEmail !== null && $bySub->id !== $byEmail->id) {
            throw new GoogleAccountConflictException(AuthConfig::GOOGLE_FAILED);
        }
        if ($bySub !== null && $byEmail === null) {
            throw new GoogleAccountConflictException(AuthConfig::GOOGLE_FAILED);
        }
        if (
            $byEmail !== null
            && $byEmail->hasGoogle()
            && $byEmail->googleSub !== $info->sub
        ) {
            throw new GoogleAccountConflictException(AuthConfig::GOOGLE_FAILED);
        }

        if ($byEmail === null && $bySub === null) {
            $user = $this->createUser($email, null, $info->sub);
            $this->touchLogin($user);

            return $this->mustFind($user->id);
        }

        $user = $byEmail ?? $bySub;
        if ($user === null) {
            throw new GoogleOAuthException(AuthConfig::GOOGLE_FAILED);
        }

        if (!$user->hasGoogle()) {
            $this->users->setGoogleSub($user->id, $info->sub);
            $dek = $this->crypto->unwrapDek($user->dekNonce, $user->encryptedDek);
            $now = $this->timestamp();
            $this->store->withUnlocked($user->id, $dek, function (PDO $pdo) use ($user, $email, $info, $now): void {
                $this->provisioner->syncAccount(
                    $pdo,
                    $user->id,
                    $email,
                    $user->passwordHash,
                    $info->sub,
                    $now,
                );
            });
        }

        $this->touchLogin($user);

        return $this->mustFind($user->id);
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
            $this->timestamp(),
        );

        return $this->mustFind($user->id);
    }

    /**
     * Identity snapshot from the unlocked user sqlite (not global DEK columns).
     *
     * @return array{email: string, has_password: bool, has_google: bool, remainder: null, open_vials: list<empty>}
     */
    public function meFromUserDb(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT email, password_hash, google_sub FROM account LIMIT 1');
        if ($stmt === false) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !isset($row['email']) || !is_string($row['email'])) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }

        $passwordHash = $row['password_hash'] ?? null;
        $googleSub = $row['google_sub'] ?? null;

        return [
            'email' => $row['email'],
            'has_password' => is_string($passwordHash) && $passwordHash !== '',
            'has_google' => is_string($googleSub) && $googleSub !== '',
            'remainder' => null,
            'open_vials' => [],
        ];
    }

    private function createUser(string $email, ?string $passwordHash, ?string $googleSub): User
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

    private function touchLogin(User $user): void
    {
        $this->users->updateLastLogin($user->id, $this->timestamp());
    }

    private function mustFind(string $id): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }

        return $user;
    }

    private function assertCredentials(string $email, string $password): void
    {
        $fields = [];
        if (!$this->emails->isValid($email)) {
            $fields['email'] = [AuthConfig::EMAIL_INVALID];
        }
        if (strlen($password) < AuthConfig::PASSWORD_MIN_LENGTH) {
            $fields['password'] = [AuthConfig::PASSWORD_TOO_SHORT];
        }
        if ($fields !== []) {
            throw new ValidationException($fields);
        }
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function isUniqueViolation(PDOException $e): bool
    {
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE');
    }
}
