<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/products', name: 'app_products_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository, CartService $cartService, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $cartQuantities = $cartService->getQuantities($user instanceof User ? $user : null, $session);

        $products = $productRepository->findAlphabeticalActiveProducts();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'cartQuantities' => $cartQuantities,
        ]);
    }
}
