<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_email_verification', methods: ['GET'])]
    public function show(Request $request, UserRepository $userRepository): Response
    {
        $pendingUserId = $request->getSession()->get('email_verification.pending_user_id');
        if (null === $pendingUserId) {
            return $this->redirectToRoute('app_login');
        }

        $pendingUser = $userRepository->find($pendingUserId);
        if (!$pendingUser instanceof User) {
            $request->getSession()->remove('email_verification.pending_user_id');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/email_verification.html.twig', [
            'pending_user' => $pendingUser,
        ]);
    }

    #[Route('/verify-email', name: 'app_email_verification_verify', methods: ['POST'])]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EmailVerificationManager $emailVerificationManager,
    ): Response {
        $session = $request->getSession();

        if (!$this->isCsrfTokenValid('email_verification_auth', (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

            return $this->redirectToRoute('app_email_verification');
        }

        $pendingUserId = $session->get('email_verification.pending_user_id');
        if (null === $pendingUserId) {
            return $this->redirectToRoute('app_login');
        }

        $pendingUser = $userRepository->find($pendingUserId);
        if (!$pendingUser instanceof User) {
            $session->remove('email_verification.pending_user_id');

            return $this->redirectToRoute('app_login');
        }

        $code = trim((string) $request->request->get('code', ''));
        if ('' === $code || !preg_match('/^\d{6}$/', $code)) {
            $this->addFlash('danger', 'Veuillez saisir un code à 6 chiffres.');

            return $this->redirectToRoute('app_email_verification');
        }

        if (!$emailVerificationManager->isCodeValid($pendingUser, $code)) {
            $this->addFlash('danger', 'Le code de vérification est incorrect ou expiré.');

            return $this->redirectToRoute('app_email_verification');
        }

        $emailVerificationManager->clearChallenge($pendingUser);
        $pendingUser->setEmailVerifiedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $session->remove('email_verification.pending_user_id');
        $this->addFlash('success', 'Votre adresse e-mail a bien été vérifiée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}
