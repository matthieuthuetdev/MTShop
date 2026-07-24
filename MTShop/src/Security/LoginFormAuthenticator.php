<?php

namespace App\Security;

use App\Entity\User;
use App\Service\TwoFactorAuthenticationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TwoFactorAuthenticationManager $twoFactorAuthenticationManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if ('' === $email) {
            throw new CustomUserMessageAuthenticationException('Saisissez votre adresse mail.');
        }

        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?RedirectResponse
    {
        $session = $request->getSession();
        $user = $token->getUser();

        if ($user instanceof UserInterface && $user instanceof User) {
            $bypassUserId = $session->get('two_factor.bypass_user_id');
            if ($user->getId() === $bypassUserId) {
                $session->remove('two_factor.bypass_user_id');
                $session->remove('two_factor.target_path');

                $targetPath = $this->getTargetPath($session, $firewallName);

                if (null !== $targetPath) {
                    return new RedirectResponse($targetPath);
                }

                return new RedirectResponse($this->urlGenerator->generate('app_home_page'));
            }

            if ($user->isTwoFactorEnabled()) {
                $code = $this->twoFactorAuthenticationManager->startChallenge($user);
                $this->entityManager->flush();
                $this->twoFactorAuthenticationManager->sendCode($user, $code);

                $session->set('two_factor.pending_user_id', $user->getId());
                $session->remove('two_factor.bypass_user_id');

                $this->tokenStorage->setToken(null);
                $session->remove(sprintf('_security_%s', $firewallName));

                return new RedirectResponse($this->urlGenerator->generate('app_two_factor'));
            }
        }

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        if (null !== $targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home_page'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
