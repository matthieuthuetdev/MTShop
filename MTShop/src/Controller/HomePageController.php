<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomePageController extends AbstractController
{
    #[Route('/', name: 'app_home_page')]
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

        return $this->render('home_page/index.html.twig', [
            'controller_name' => 'HomePageController',
            'promotedProducts' => $productRepository->findPromotedActiveProducts(3),
            'cartQuantities' => $cartQuantities,
        ]);
    }
}
