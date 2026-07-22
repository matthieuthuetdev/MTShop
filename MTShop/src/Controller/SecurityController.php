<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home_page');
        }

        return $this->render('security/signin.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the firewall logout.');
    }

    #[Route('/clear-session', name: 'app_clear_session', methods: ['POST'])]
    public function clearSession(Request $request): Response
    {
        if ($this->isCsrfTokenValid('clear_session', (string) $request->request->get('_token', ''))) {
            $request->getSession()->invalidate();
        }

        return $this->redirectToRoute('app_login');
    }
}
