<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class PasswordAuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly EmailNormalizer $emails,
        private readonly UserFactory $factory,
    ) {
    }

    public function register(string $email, string $password): User
    {
        $email = $this->emails->normalize($email);
        $this->assertCredentials($email, $password);

        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException(['email' => [AuthConfig::EMAIL_TAKEN]]);
        }

        $user = $this->factory->create($email, $this->passwords->hash($password), null);

        return $this->factory->touchLogin($user);
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

        return $this->factory->touchLogin($user);
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
}
