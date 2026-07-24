<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class LogOutController extends AbstractController
{
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function __invoke(RequestStack $requestStack, TokenStorageInterface $tokenStorage): Response
    {
        $tokenStorage->setToken(null);

        $session = $requestStack->getSession();
        if ($session !== null && $session->isStarted()) {
            $session->invalidate();
        }

        return $this->redirectToRoute('app_home_page');
    }
}
