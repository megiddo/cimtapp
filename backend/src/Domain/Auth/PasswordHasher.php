<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Argon2id hashing. Testing uses a smaller memory cost so Infection stays fast;
 * password_verify accepts hashes produced with either option set.
 */
final class PasswordHasher
{
    /**
     * Dummy Argon2id hash used when the email is unknown so verify still runs.
     * Password is not a real user secret.
     */
    private const DUMMY_HASH = '$argon2id$v=19$m=8192,t=1,p=1$Q2FHbnFOdjZuYURMT0dORQ$yhFQJgqfBrPEPCNnwkmu+i/9kyBEdF321o4/ou3QNRc';

    /** @var array<string, int> */
    private array $options;

    public function __construct(bool $interactive = true)
    {
        $this->options = $interactive
            ? []
            : [
                'memory_cost' => 8 * 1024,
                'time_cost' => 1,
                'threads' => 1,
            ];
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, $this->options);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash password.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function dummyHash(): string
    {
        return self::DUMMY_HASH;
    }

    public function isArgon2id(string $hash): bool
    {
        return str_starts_with($hash, '$argon2id$');
    }
}
