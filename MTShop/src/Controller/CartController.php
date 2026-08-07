<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    #[Route('/my-cart', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartRepository $cartRepository): Response
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $carts = $cartRepository->findBy(['customer' => $user], ['id' => 'DESC']);
        $items = [];
        $totalItems = 0;
        $totalAmount = 0.0;

        foreach ($carts as $cart) {
            $product = $cart->getProduct();
            if (null === $product) {
                continue;
            }

            $unitPrice = (float) $product->getPrice();
            $promotion = max(0, min(100, (int) $product->getPromotion()));
            $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
            $lineTotal = $discountedUnitPrice * $cart->getQuantity();

            $items[] = [
                'cart' => $cart,
                'product' => $product,
                'quantity' => $cart->getQuantity(),
                'unitPrice' => $unitPrice,
                'promotion' => $promotion,
                'discountedUnitPrice' => $discountedUnitPrice,
                'lineTotal' => $lineTotal,
            ];

            $totalItems += $cart->getQuantity();
            $totalAmount += $lineTotal;
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
        ]);
    }

    #[Route('/my-cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        Request $request,
        Product $product,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_add_'.$product->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');
            return $this->redirectToRoute('app_cart_index');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $cart = $cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
        if (null === $cart) {
            $cart = new Cart();
            $cart->setCustomer($user);
            $cart->setProduct($product);
            $cart->setQuantity(1);
            $entityManager->persist($cart);
        } else {
            $cart->setQuantity($cart->getQuantity() + 1);
        }

        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            $totals = $cartRepository->findBy(['customer' => $user]);
            $totalItems = 0;
            $totalAmount = 0.0;
            foreach ($totals as $item) {
                $productItem = $item->getProduct();
                if (!$productItem) {
                    continue;
                }
                $unitPrice = (float) $productItem->getPrice();
                $promotion = max(0, min(100, (int) $productItem->getPromotion()));
                $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
                $totalItems += $item->getQuantity();
                $totalAmount += $discountedUnitPrice * $item->getQuantity();
            }

            return new JsonResponse([
                'quantity' => $cart->getQuantity(),
                'totalItems' => $totalItems,
                'totalAmount' => $totalAmount,
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/my-cart/{id}/increase', name: 'app_cart_increase', methods: ['POST'])]
    public function increase(Request $request, Cart $cart, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_increase_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');
            return $this->redirectToRoute('app_cart_index');
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $cart->setQuantity($cart->getQuantity() + 1);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            $totals = $cartRepository->findBy(['customer' => $user]);
            $totalItems = 0;
            $totalAmount = 0.0;
            foreach ($totals as $item) {
                $productItem = $item->getProduct();
                if (!$productItem) {
                    continue;
                }
                $unitPrice = (float) $productItem->getPrice();
                $promotion = max(0, min(100, (int) $productItem->getPromotion()));
                $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
                $totalItems += $item->getQuantity();
                $totalAmount += $discountedUnitPrice * $item->getQuantity();
            }

            return new JsonResponse([
                'quantity' => $cart->getQuantity(),
                'totalItems' => $totalItems,
                'totalAmount' => $totalAmount,
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/my-cart/{id}/decrease', name: 'app_cart_decrease', methods: ['POST'])]
    public function decrease(Request $request, Cart $cart, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_decrease_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');
            return $this->redirectToRoute('app_cart_index');
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->redirectToRoute('app_cart_index');
        }

        if ($cart->getQuantity() <= 1) {
            $entityManager->remove($cart);
            $quantity = 0;
        } else {
            $cart->setQuantity($cart->getQuantity() - 1);
            $quantity = $cart->getQuantity();
        }

        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            $totals = $cartRepository->findBy(['customer' => $user]);
            $totalItems = 0;
            $totalAmount = 0.0;
            foreach ($totals as $item) {
                $productItem = $item->getProduct();
                if (!$productItem) {
                    continue;
                }
                $unitPrice = (float) $productItem->getPrice();
                $promotion = max(0, min(100, (int) $productItem->getPromotion()));
                $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
                $totalItems += $item->getQuantity();
                $totalAmount += $discountedUnitPrice * $item->getQuantity();
            }

            return new JsonResponse([
                'quantity' => $quantity,
                'totalItems' => $totalItems,
                'totalAmount' => $totalAmount,
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/my-cart/product/{id}/decrease', name: 'app_cart_decrease_by_product', methods: ['POST'])]
    public function decreaseByProduct(Request $request, Product $product, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_'.$product->getId(), (string) $request->request->get('_token', ''))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
        if (null === $cart) {
            return new JsonResponse(['quantity' => 0, 'totalItems' => 0, 'totalAmount' => 0.0]);
        }

        if ($cart->getQuantity() <= 1) {
            $entityManager->remove($cart);
            $quantity = 0;
        } else {
            $cart->setQuantity($cart->getQuantity() - 1);
            $quantity = $cart->getQuantity();
        }

        $entityManager->flush();

        $totals = $cartRepository->findBy(['customer' => $user]);
        $totalItems = 0;
        $totalAmount = 0.0;
        foreach ($totals as $item) {
            $productItem = $item->getProduct();
            if (!$productItem) {
                continue;
            }
            $unitPrice = (float) $productItem->getPrice();
            $promotion = max(0, min(100, (int) $productItem->getPromotion()));
            $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
            $totalItems += $item->getQuantity();
            $totalAmount += $discountedUnitPrice * $item->getQuantity();
        }

        return new JsonResponse([
            'quantity' => $quantity,
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
        ]);
    }

    #[Route('/my-cart/{id}/remove', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Request $request, Cart $cart, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');
            return $this->redirectToRoute('app_cart_index');
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $entityManager->remove($cart);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            $totals = $cartRepository->findBy(['customer' => $user]);
            $totalItems = 0;
            $totalAmount = 0.0;
            foreach ($totals as $item) {
                $productItem = $item->getProduct();
                if (!$productItem) {
                    continue;
                }
                $unitPrice = (float) $productItem->getPrice();
                $promotion = max(0, min(100, (int) $productItem->getPromotion()));
                $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;
                $totalItems += $item->getQuantity();
                $totalAmount += $discountedUnitPrice * $item->getQuantity();
            }

            return new JsonResponse([
                'quantity' => 0,
                'totalItems' => $totalItems,
                'totalAmount' => $totalAmount,
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }
}
