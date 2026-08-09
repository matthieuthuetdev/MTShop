<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        PasswordResetService $passwordResetService,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
    ): Response {
        $successMessage = null;
        $email = '';

        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User) {
                $token = $passwordResetService->createResetToken($user);
                $entityManager->flush();

                $resetUrl = $this->generateUrl(
                    'app_forgot_password_approval',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $emailMessage = (new TemplatedEmail())
                    ->from('mthuet.pro@gmail.com')
                    ->to($user->getEmail() ?? '')
                    ->subject('Réinitialisation de votre mot de passe MTShop')
                    ->htmlTemplate('mail/password_reset.html.twig')
                    ->context([
                        'user' => $user,
                        'resetUrl' => $resetUrl,
                    ]);

                $mailer->send($emailMessage);
                $successMessage = sprintf(
                    'Un e-mail de réinitialisation a été envoyé à %s.',
                    $user->getEmail() ?? $email
                );
            } else {
                $successMessage = 'Si un compte correspondant à cette adresse existe, un e-mail vous a été envoyé.';
            }
        }

        return $this->render('security/forgot_password_request.html.twig', [
            'successMessage' => $successMessage,
            'email' => $email,
        ]);
    }

    #[Route('/forgot-password/approval/{token}', name: 'app_forgot_password_approval', methods: ['GET', 'POST'])]
    public function approval(
        Request $request,
        string $token,
        UserRepository $userRepository,
        PasswordResetService $passwordResetService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['passwordResetToken' => $token]);
        if (!$user instanceof User || !$passwordResetService->isTokenValid($user, $token)) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $decision = (string) $request->request->get('decision', '');
            if ('approve' === $decision) {
                $passwordResetService->approveReset($user, $token);
                $entityManager->flush();

                return $this->redirectToRoute('app_forgot_password_update', ['token' => $token]);
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password_approval.html.twig', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    #[Route('/forgot-password/update/{token}', name: 'app_forgot_password_update', methods: ['GET', 'POST'])]
    public function update(
        Request $request,
        string $token,
        UserRepository $userRepository,
        PasswordResetService $passwordResetService,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['passwordResetToken' => $token]);
        if (!$user instanceof User || !$passwordResetService->isTokenValid($user, $token)) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isPasswordResetApproved()) {
            return $this->render('security/forgot_password_waiting.html.twig', [
                'token' => $token,
                'user' => $user,
            ]);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $plainPassword = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if ('' === $plainPassword || '' === $confirmPassword) {
                $error = 'Veuillez renseigner les deux champs.';
            } elseif ($plainPassword !== $confirmPassword) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } elseif (mb_strlen($plainPassword) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $passwordResetService->clearReset($user);
                $entityManager->flush();

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/forgot_password_update.html.twig', [
            'token' => $token,
            'error' => $error,
        ]);
    }
}
