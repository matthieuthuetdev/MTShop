<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomePageController extends AbstractController
{
    #[Route('/', name: 'app_home_page')]
    public function index(ProductRepository $productRepository, CartService $cartService, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $cartQuantities = $cartService->getQuantities($user instanceof User ? $user : null, $session);

        return $this->render('home_page/index.html.twig', [
            'controller_name' => 'HomePageController',
            'promotedProducts' => $productRepository->findPromotedActiveProducts(3),
            'cartQuantities' => $cartQuantities,
        ]);
    }
}
