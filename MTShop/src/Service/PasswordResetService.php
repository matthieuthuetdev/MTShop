<?php

namespace App\Service;

use App\Entity\User;

final class PasswordResetService
{
    public function createResetToken(User $user): string
    {
        $token = bin2hex(random_bytes(16));
        $user->setPasswordResetToken($token);
        $user->setPasswordResetApproved(false);
        $user->setPasswordResetRequestedAt(new \DateTimeImmutable());
        $user->setPasswordResetApprovedAt(null);
        $user->setPasswordResetTokenExpiresAt((new \DateTimeImmutable())->modify('+2 hours'));

        return $token;
    }

    public function approveReset(User $user, string $token): bool
    {
        if (!$this->isTokenValid($user, $token)) {
            return false;
        }

        $user->setPasswordResetApproved(true);
        $user->setPasswordResetApprovedAt(new \DateTimeImmutable());

        return true;
    }

    public function isApproved(User $user, string $token): bool
    {
        if (!$this->isTokenValid($user, $token)) {
            return false;
        }

        return $user->isPasswordResetApproved();
    }

    public function isTokenValid(User $user, string $token): bool
    {
        if ('' === trim($token)) {
            return false;
        }

        $storedToken = $user->getPasswordResetToken();
        if (null === $storedToken || !hash_equals($storedToken, $token)) {
            return false;
        }

        $expiresAt = $user->getPasswordResetTokenExpiresAt();
        if (null === $expiresAt) {
            return false;
        }

        return new \DateTimeImmutable() <= $expiresAt;
    }

    public function clearReset(User $user): void
    {
        $user->setPasswordResetToken(null);
        $user->setPasswordResetApproved(false);
        $user->setPasswordResetRequestedAt(null);
        $user->setPasswordResetApprovedAt(null);
        $user->setPasswordResetTokenExpiresAt(null);
    }
}
