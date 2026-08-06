<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin/sellers', name: 'app_admin_sellers', methods: ['GET'])]
    public function sellers(UserRepository $userRepository): Response
    {
        $sellers = $userRepository->findBy(['role' => 'SELLER']);

        return $this->render('admin/sellers.html.twig', [
            'sellers' => $sellers,
        ]);
    }
}
