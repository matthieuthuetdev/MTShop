<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/products', name: 'app_products_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository, CartRepository $cartRepository): Response
    {
        $user = $this->getUser();
        $cartQuantities = [];

        if ($user instanceof User) {
            $carts = $cartRepository->findBy(['customer' => $user]);
            foreach ($carts as $cart) {
                $product = $cart->getProduct();
                if (null !== $product) {
                    $cartQuantities[$product->getId()] = $cart->getQuantity();
                }
            }
        }

        $products = $productRepository->findAlphabeticalActiveProducts();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'cartQuantities' => $cartQuantities,
        ]);
    }
}
