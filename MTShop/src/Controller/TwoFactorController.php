<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use App\Service\TwoFactorAuthenticationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class TwoFactorController extends AbstractController
{
    #[Route('/two-factor', name: 'app_two_factor', methods: ['GET'])]
    public function show(Request $request, UserRepository $userRepository): Response
    {
        $pendingUserId = $request->getSession()->get('two_factor.pending_user_id');
        if (null === $pendingUserId) {
            return $this->redirectToRoute('app_login');
        }

        $pendingUser = $userRepository->find($pendingUserId);
        if (!$pendingUser instanceof User) {
            $request->getSession()->remove('two_factor.pending_user_id');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/two_factor.html.twig', [
            'pending_user' => $pendingUser,
        ]);
    }

    #[Route('/two-factor', name: 'app_two_factor_verify', methods: ['POST'])]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        TwoFactorAuthenticationManager $twoFactorAuthenticationManager,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator,
    ): Response {
        $session = $request->getSession();

        if (!$this->isCsrfTokenValid('two_factor_auth', (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Le code de vérification n’a pas pu être validé.');

            return $this->redirectToRoute('app_two_factor');
        }

        $pendingUserId = $session->get('two_factor.pending_user_id');
        if (null === $pendingUserId) {
            return $this->redirectToRoute('app_login');
        }

        $pendingUser = $userRepository->find($pendingUserId);
        if (!$pendingUser instanceof User) {
            $session->remove('two_factor.pending_user_id');

            return $this->redirectToRoute('app_login');
        }

        $code = trim((string) $request->request->get('code', ''));
        if ('' === $code || !preg_match('/^\d{6}$/', $code)) {
            $this->addFlash('danger', 'Veuillez saisir un code à 6 chiffres.');

            return $this->redirectToRoute('app_two_factor');
        }

        if (!$twoFactorAuthenticationManager->isCodeValid($pendingUser, $code)) {
            $this->addFlash('danger', 'Le code de vérification est incorrect ou expiré.');

            return $this->redirectToRoute('app_two_factor');
        }

        $twoFactorAuthenticationManager->clearChallenge($pendingUser);
        $entityManager->flush();

        $session->remove('two_factor.pending_user_id');
        $session->set('two_factor.bypass_user_id', $pendingUser->getId());

        return $userAuthenticator->authenticateUser($pendingUser, $loginFormAuthenticator, $request);
    }
}
