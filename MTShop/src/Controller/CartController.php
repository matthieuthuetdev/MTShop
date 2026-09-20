<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    #[Route('/my-cart', name: 'app_cart_index', methods: ['GET'])]
    public function index(SessionInterface $session, CartRepository $cartRepository, ProductRepository $productRepository): Response
    {
        $user = $this->getUser();
        $items = [];

        if ($user instanceof User) {
            foreach ($cartRepository->findBy(['customer' => $user], ['id' => 'DESC']) as $cart) {
                $product = $cart->getProduct();
                if ($product instanceof Product) {
                    $items[] = $this->buildItem($cart, $product, $cart->getQuantity());
                }
            }
        } else {
            foreach ($session->get('mtshop_cart', []) as $productId => $quantity) {
                $product = $productRepository->find((int) $productId);
                if ($product instanceof Product && (int) $quantity > 0) {
                    $items[] = $this->buildItem(null, $product, (int) $quantity);
                }
            }
        }

        $totalItems = 0;
        $totalAmount = 0.0;
        foreach ($items as $item) {
            $totalItems += $item['quantity'];
            $totalAmount += $item['lineTotal'];
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
            'isGuestCart' => !$user instanceof User,
        ]);
    }

    #[Route('/my-cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, Product $product, CartRepository $cartRepository, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_'.$product->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $quantity = $cartService->addGuest($session, (int) $product->getId());

            return $this->cartResponse($request, $cartService, $session, $quantity, $product);
        }

        $cart = $cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
        if (!$cart instanceof Cart) {
            $cart = (new Cart())->setCustomer($user)->setProduct($product)->setQuantity(1);
            $entityManager->persist($cart);
        } else {
            $cart->setQuantity($cart->getQuantity() + 1);
        }
        $entityManager->flush();

        return $this->cartResponse($request, $cartService, $session, $cart->getQuantity(), $product, $user);
    }

    #[Route('/my-cart/{id}/increase', name: 'app_cart_increase', methods: ['POST'])]
    public function increase(Request $request, Cart $cart, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_increase_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.');
        }
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->cartError($request, 'Accès refusé.', Response::HTTP_FORBIDDEN);
        }
        $cart->setQuantity($cart->getQuantity() + 1);
        $entityManager->flush();

        return $this->cartResponse($request, $cartService, $session, $cart->getQuantity(), $cart->getProduct(), $user);
    }

    #[Route('/my-cart/{id}/decrease', name: 'app_cart_decrease', methods: ['POST'])]
    public function decrease(Request $request, Cart $cart, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_decrease_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.');
        }
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->cartError($request, 'Accès refusé.', Response::HTTP_FORBIDDEN);
        }
        $product = $cart->getProduct();
        if ($cart->getQuantity() <= 1) {
            $entityManager->remove($cart);
            $quantity = 0;
        } else {
            $cart->setQuantity($cart->getQuantity() - 1);
            $quantity = $cart->getQuantity();
        }
        $entityManager->flush();

        return $this->cartResponse($request, $cartService, $session, $quantity, $product, $user);
    }

    #[Route('/my-cart/product/{id}/decrease', name: 'app_cart_decrease_by_product', methods: ['POST'])]
    public function decreaseByProduct(Request $request, Product $product, CartRepository $cartRepository, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_'.$product->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.', Response::HTTP_FORBIDDEN);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            $quantity = $cartService->decreaseGuest($session, (int) $product->getId());

            return $this->cartResponse($request, $cartService, $session, $quantity, $product);
        }
        $cart = $cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
        if (!$cart instanceof Cart) {
            return $this->cartResponse($request, $cartService, $session, 0, $product, $user);
        }
        if ($cart->getQuantity() <= 1) {
            $entityManager->remove($cart);
            $quantity = 0;
        } else {
            $cart->setQuantity($cart->getQuantity() - 1);
            $quantity = $cart->getQuantity();
        }
        $entityManager->flush();

        return $this->cartResponse($request, $cartService, $session, $quantity, $product, $user);
    }

    #[Route('/my-cart/product/{id}/remove', name: 'app_cart_remove_by_product', methods: ['POST'])]
    public function removeByProduct(Request $request, Product $product, CartRepository $cartRepository, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_'.$product->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.', Response::HTTP_FORBIDDEN);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            $cartService->removeGuest($session, (int) $product->getId());

            return $this->cartResponse($request, $cartService, $session, 0, $product);
        }
        $cart = $cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
        if ($cart instanceof Cart) {
            $entityManager->remove($cart);
            $entityManager->flush();
        }

        return $this->cartResponse($request, $cartService, $session, 0, $product, $user);
    }

    #[Route('/my-cart/{id}/remove', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Request $request, Cart $cart, EntityManagerInterface $entityManager, CartService $cartService, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_'.$cart->getId(), (string) $request->request->get('_token', ''))) {
            return $this->cartError($request, 'Votre demande n\'a pas pu être vérifiée.');
        }
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() !== $cart->getCustomer()?->getId()) {
            return $this->cartError($request, 'Accès refusé.', Response::HTTP_FORBIDDEN);
        }
        $product = $cart->getProduct();
        $entityManager->remove($cart);
        $entityManager->flush();

        return $this->cartResponse($request, $cartService, $session, 0, $product, $user);
    }

    private function buildItem(?Cart $cart, Product $product, int $quantity): array
    {
        $unitPrice = (float) $product->getPrice();
        $promotion = max(0, min(100, $product->getPromotion()));
        $discountedUnitPrice = $unitPrice * (100 - $promotion) / 100;

        return [
            'cart' => $cart,
            'product' => $product,
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'promotion' => $promotion,
            'discountedUnitPrice' => $discountedUnitPrice,
            'lineTotal' => $discountedUnitPrice * $quantity,
        ];
    }

    private function cartResponse(Request $request, CartService $cartService, SessionInterface $session, int $quantity, ?Product $product, ?User $user = null): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $totals = $cartService->getTotals($user, $session);

        return new JsonResponse([
            'quantity' => $quantity,
            'totalItems' => $totals['totalItems'],
            'totalAmount' => $totals['totalAmount'],
        ]);
    }

    private function cartError(Request $request, string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => $message], $status);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('app_cart_index');
    }
}
