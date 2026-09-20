<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'mtshop_cart';

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<int, int> */
    public function getQuantities(?User $user, SessionInterface $session): array
    {
        if ($user instanceof User) {
            $quantities = [];
            foreach ($this->cartRepository->findBy(['customer' => $user]) as $cart) {
                $productId = $cart->getProduct()?->getId();
                if (null !== $productId) {
                    $quantities[$productId] = $cart->getQuantity();
                }
            }

            return $quantities;
        }

        return $this->getGuestQuantities($session);
    }

    /** @return array<int, int> */
    public function getGuestQuantities(SessionInterface $session): array
    {
        $rawCart = $session->get(self::SESSION_KEY, []);
        $quantities = [];

        foreach (is_array($rawCart) ? $rawCart : [] as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = (int) $quantity;
            if ($productId > 0 && $quantity > 0) {
                $quantities[$productId] = $quantity;
            }
        }

        return $quantities;
    }

    /** @return array{totalItems: int, totalAmount: float} */
    public function getTotals(?User $user, SessionInterface $session): array
    {
        $totalItems = 0;
        $totalAmount = 0.0;
        foreach ($this->getQuantities($user, $session) as $productId => $quantity) {
            $product = $this->productRepository->find($productId);
            if (!$product instanceof Product) {
                continue;
            }

            $promotion = max(0, min(100, $product->getPromotion()));
            $totalItems += $quantity;
            $totalAmount += (float) $product->getPrice() * (100 - $promotion) / 100 * $quantity;
        }

        return ['totalItems' => $totalItems, 'totalAmount' => $totalAmount];
    }

    public function addGuest(SessionInterface $session, int $productId): int
    {
        $quantities = $this->getGuestQuantities($session);
        $quantities[$productId] = ($quantities[$productId] ?? 0) + 1;
        $session->set(self::SESSION_KEY, $quantities);

        return $quantities[$productId];
    }

    public function decreaseGuest(SessionInterface $session, int $productId): int
    {
        $quantities = $this->getGuestQuantities($session);
        $quantity = max(0, ($quantities[$productId] ?? 0) - 1);
        if (0 === $quantity) {
            unset($quantities[$productId]);
        } else {
            $quantities[$productId] = $quantity;
        }
        $session->set(self::SESSION_KEY, $quantities);

        return $quantity;
    }

    public function removeGuest(SessionInterface $session, int $productId): void
    {
        $quantities = $this->getGuestQuantities($session);
        unset($quantities[$productId]);
        $session->set(self::SESSION_KEY, $quantities);
    }

    public function mergeGuestCart(User $user, SessionInterface $session): void
    {
        foreach ($this->getGuestQuantities($session) as $productId => $quantity) {
            $product = $this->productRepository->find($productId);
            if (!$product instanceof Product) {
                continue;
            }

            $cart = $this->cartRepository->findOneBy(['customer' => $user, 'product' => $product]);
            if (!$cart instanceof Cart) {
                $cart = (new Cart())
                    ->setCustomer($user)
                    ->setProduct($product)
                    ->setQuantity($quantity);
                $this->entityManager->persist($cart);
            } else {
                $cart->setQuantity($cart->getQuantity() + $quantity);
            }
        }

        $session->remove(self::SESSION_KEY);
        $this->entityManager->flush();
    }
}
