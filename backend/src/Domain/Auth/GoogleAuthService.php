<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class GoogleAuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmailNormalizer $emails,
        private readonly UserFactory $factory,
        private readonly AccountSyncService $sync,
    ) {
    }

    public function login(GoogleUserInfo $info): User
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
            $user = $this->factory->create($email, null, $info->sub);

            return $this->factory->touchLogin($user);
        }

        $user = $byEmail ?? $bySub;
        if ($user === null) {
            throw new GoogleOAuthException(AuthConfig::GOOGLE_FAILED);
        }

        if (!$user->hasGoogle()) {
            $this->sync->attachGoogle($user, $email, $info->sub);
        }

        return $this->factory->touchLogin($user);
    }
}
