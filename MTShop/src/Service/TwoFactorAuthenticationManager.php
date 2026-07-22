<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final class TwoFactorAuthenticationManager
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function startChallenge(User $user): string
    {
        $code = $this->generateCode();

        $user->setTwoFactorCodeHash(hash('sha256', $code));
        $user->setTwoFactorCodeExpiresAt(new \DateTimeImmutable('+10 minutes'));

        return $code;
    }

    public function isCodeValid(User $user, string $code): bool
    {
        $expiresAt = $user->getTwoFactorCodeExpiresAt();
        $hash = $user->getTwoFactorCodeHash();

        if (null === $expiresAt || null === $hash) {
            return false;
        }

        if (new \DateTimeImmutable() > $expiresAt) {
            return false;
        }

        return hash_equals($hash, hash('sha256', trim($code)));
    }

    public function clearChallenge(User $user): void
    {
        $user->setTwoFactorCodeHash(null);
        $user->setTwoFactorCodeExpiresAt(null);
    }

    public function sendCode(User $user, string $code): void
    {
        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail() ?? '')
            ->subject('code de validation MTShop')
            ->htmlTemplate('mail/two_factor_code.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
            ]);

        $this->mailer->send($email);
    }
}
