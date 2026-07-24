<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SignInController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home_page');
        }

        $lastEmail = '';
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('login', (string) $request->request->get('_token', ''))) {
                $error = 'Votre demande n’a pas pu être vérifiée.';
            } else {
                $email = mb_strtolower(trim((string) $request->request->get('email', '')));
                $password = (string) $request->request->get('password', '');
                $lastEmail = $email;

                if ('' === $email || '' === $password) {
                    $error = 'Veuillez renseigner votre adresse mail et votre mot de passe.';
                } else {
                    $user = $userRepository->findOneBy(['email' => $email]);

                    if (!$user instanceof User || !$passwordHasher->isPasswordValid($user, $password)) {
                        $error = 'Adresse mail ou mot de passe incorrect.';
                    } else {
                        $tokenStorage->setToken(new PostAuthenticationToken($user, 'main', $user->getRoles()));

                        return $this->redirectToRoute('app_home_page');
                    }
                }
            }
        }

        return $this->render('security/signin.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
        ]);
    }
}
