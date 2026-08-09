<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use App\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    #[Route('/my-orders/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $order->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $orderItems = [];
        $totalItems = 0;

        foreach ($order->getOrderItems() as $orderItem) {
            $orderItems[] = [
                'orderItem' => $orderItem,
                'product' => $orderItem->getProduct(),
                'quantity' => (int) $orderItem->getQuantity(),
                'unitPrice' => (float) $orderItem->getUnitPrice(),
                'lineTotal' => (float) $orderItem->getTotalPrice(),
            ];
            $totalItems += (int) $orderItem->getQuantity();
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
            'orderItems' => $orderItems,
            'totalItems' => $totalItems,
            'statusLabel' => $checkoutService->getOrderStatusLabel($order->getStatus()),
            'deliveryDateText' => $checkoutService->getDeliveryDateText($order),
        ]);
    }

    #[Route('/my-orders', name: 'app_orders_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findBy(['customer' => $user], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $orderCards = [];

        foreach ($orders as $order) {
            $totalItems = 0;
            $availableReorderItems = 0;

            foreach ($order->getOrderItems() as $orderItem) {
                $totalItems += (int) $orderItem->getQuantity();

                if ($checkoutService->isProductAvailableForReorder($orderItem->getProduct())) {
                    ++$availableReorderItems;
                }
            }

            $orderCards[] = [
                'order' => $order,
                'totalItems' => $totalItems,
                'statusLabel' => $checkoutService->getOrderStatusLabel($order->getStatus()),
                'canCancel' => $checkoutService->canCancelOrder($order),
                'canRefund' => $checkoutService->canRequestRefund($order),
                'canReorder' => $availableReorderItems > 0,
            ];
        }

        return $this->render('order/index.html.twig', [
            'orderCards' => $orderCards,
        ]);
    }

    #[Route('/checkout', name: 'app_checkout_summary', methods: ['GET'])]
    public function summary(CartRepository $cartRepository, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $carts = $cartRepository->findBy(['customer' => $user], ['id' => 'DESC']);
        $summary = $checkoutService->summarizeCart($carts);
        if (empty($summary['items'])) {
            return $this->redirectToRoute('app_home_page');
        }

        $deliveryOptions = $checkoutService->getDeliveryOptions($summary['subtotal']);
        $shippingAddress = trim((string) $user->getShippingAddress());
        $billingAddress = trim((string) $user->getBillingAddress());

        return $this->render('order/summary.html.twig', [
            'items' => $summary['items'],
            'totalItems' => $summary['totalItems'],
            'subtotal' => $summary['subtotal'],
            'deliveryOptions' => $deliveryOptions,
            'selectedDelivery' => 'basic',
            'shippingAddress' => $shippingAddress,
            'billingAddress' => $billingAddress,
            'hasRequiredAddresses' => '' !== $shippingAddress && '' !== $billingAddress,
        ]);
    }

    #[Route('/checkout', name: 'app_checkout_place', methods: ['POST'])]
    public function place(
        Request $request,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager,
        CheckoutService $checkoutService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('checkout_place', (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');

            return $this->redirectToRoute('app_checkout_summary');
        }

        $carts = $cartRepository->findBy(['customer' => $user], ['id' => 'DESC']);
        $summary = $checkoutService->summarizeCart($carts);
        $shippingAddress = trim((string) $user->getShippingAddress());
        $billingAddress = trim((string) $user->getBillingAddress());

        if (empty($summary['items'])) {
            return $this->redirectToRoute('app_home_page');
        }

        if ('' === $shippingAddress || '' === $billingAddress) {
            $this->addFlash('danger', 'Vous devez renseigner votre adresse de livraison et votre adresse de facturation avant de commander.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $deliveryCode = (string) $request->request->get('delivery_method', 'basic');
        $deliveryOption = $checkoutService->resolveDeliveryOption($deliveryCode, $summary['subtotal']);

        if (null === $deliveryOption) {
            $this->addFlash('danger', 'L’offre de livraison sélectionnée est invalide.');

            return $this->redirectToRoute('app_checkout_summary');
        }

        if (false === $deliveryOption['available']) {
            $this->addFlash('danger', 'Cette offre de livraison n’est pas disponible maintenant.');

            return $this->redirectToRoute('app_checkout_summary');
        }

        $order = $checkoutService->createOrder($user, $summary['items'], $summary['subtotal'], $deliveryOption);
        $entityManager->persist($order);

        foreach ($summary['items'] as $item) {
            $entityManager->remove($item['cart']);
        }

        $entityManager->flush();
        try {
            $checkoutService->sendOrderNotification($order, $summary, $deliveryOption);
        } catch (\Throwable $exception) {
            $this->addFlash('warning', 'La commande est enregistrée, mais l’e-mail de notification n’a pas pu être envoyé.');
        }

        return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
    }

    #[Route('/checkout/{id}/confirmation', name: 'app_order_confirmation', methods: ['GET'])]
    public function confirmation(Order $order, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $order->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $deliveryDateText = $checkoutService->getDeliveryDateText($order);
        $subtotal = (float) $order->getTotalAmount() - (float) ($order->getDeliveryFee() ?? '0');
        $deliveryOptions = $checkoutService->getDeliveryOptions($subtotal, $order->getCreatedAt());
        $deliveryOption = $deliveryOptions[$order->getDeliveryMethod() ?? 'basic'] ?? $deliveryOptions['basic'];

        return $this->render('order/confirmation.html.twig', [
            'order' => $order,
            'deliveryDateText' => $deliveryDateText,
            'deliveryOption' => $deliveryOption,
        ]);
    }

    #[Route('/my-orders/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(Order $order, Request $request, EntityManagerInterface $entityManager, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $order->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_order_'.$order->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

            return $this->redirectToRoute('app_orders_index');
        }

        if (!$checkoutService->canCancelOrder($order)) {
            $this->addFlash('warning', 'Cette commande ne peut plus être annulée.');

            return $this->redirectToRoute('app_orders_index');
        }

        $order->setStatus('cancelled');
        $entityManager->flush();

        $this->addFlash('success', sprintf('La commande #%d a bien été annulée.', $order->getId()));

        return $this->redirectToRoute('app_orders_index');
    }

    #[Route('/my-orders/{id}/reorder', name: 'app_order_reorder', methods: ['POST'])]
    public function reorder(
        Order $order,
        Request $request,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager,
        CheckoutService $checkoutService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $order->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('reorder_order_'.$order->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

            return $this->redirectToRoute('app_orders_index');
        }

        $addedCount = 0;
        $skippedProducts = [];

        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();

            if (!$checkoutService->isProductAvailableForReorder($product)) {
                if (null !== $product?->getName()) {
                    $skippedProducts[] = $product->getName();
                }
                continue;
            }

            $existingCart = $cartRepository->findOneBy([
                'customer' => $user,
                'product' => $product,
            ]);

            $existingQuantity = $existingCart?->getQuantity() ?? 0;
            $availableStock = max(0, (int) $product->getStock() - $existingQuantity);
            if ($availableStock <= 0) {
                $skippedProducts[] = (string) $product->getName();
                continue;
            }

            $quantityToAdd = min((int) $orderItem->getQuantity(), $availableStock);
            if ($quantityToAdd <= 0) {
                $skippedProducts[] = (string) $product->getName();
                continue;
            }

            $cart = $existingCart ?? new Cart();
            if (!$existingCart instanceof Cart) {
                $cart->setCustomer($user);
                $cart->setProduct($product);
                $cart->setQuantity(0);
                $entityManager->persist($cart);
            }

            $cart->setQuantity((int) $cart->getQuantity() + $quantityToAdd);
            $addedCount += $quantityToAdd;
        }

        if ($addedCount > 0) {
            $entityManager->flush();
        }

        if ($addedCount <= 0) {
            $this->addFlash('warning', 'Aucun article de cette commande n\'est encore disponible dans la boutique.');

            return $this->redirectToRoute('app_orders_index');
        }

        if ([] !== $skippedProducts) {
            $this->addFlash('warning', 'Certains articles n\'ont pas pu être ajoutés car ils ne sont plus disponibles.');
        }

        $this->addFlash('success', 'Les articles disponibles ont été ajoutés à votre panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/my-orders/{id}/refund', name: 'app_order_refund', methods: ['GET', 'POST'])]
    public function refund(Order $order, Request $request, CheckoutService $checkoutService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $order->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$checkoutService->canRequestRefund($order)) {
            $this->addFlash('warning', 'Le remboursement n\'est disponible que pour les commandes livrées.');

            return $this->redirectToRoute('app_orders_index');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('refund_order_'.$order->getId(), (string) $request->request->get('_token', ''))) {
                $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

                return $this->redirectToRoute('app_order_refund', ['id' => $order->getId()]);
            }

            $selectedItems = $checkoutService->buildRefundSelection(
                $order,
                (array) $request->request->all('items'),
                (array) $request->request->all('reasons'),
            );

            if ([] === $selectedItems) {
                $this->addFlash('warning', 'Sélectionnez au moins un article et renseignez une raison pour chaque article choisi.');

                return $this->redirectToRoute('app_order_refund', ['id' => $order->getId()]);
            }

            try {
                $checkoutService->sendRefundRequestNotification($order, $selectedItems);
                $this->addFlash('success', 'Votre demande de remboursement a bien été envoyée.');
            } catch (\Throwable $exception) {
                $this->addFlash('danger', 'La demande n\'a pas pu être envoyée pour le moment.');

                return $this->redirectToRoute('app_order_refund', ['id' => $order->getId()]);
            }

            return $this->redirectToRoute('app_orders_index');
        }

        $refundItems = [];
        $totalItems = 0;

        foreach ($order->getOrderItems() as $orderItem) {
            $totalItems += (int) $orderItem->getQuantity();
            $refundItems[] = [
                'orderItem' => $orderItem,
                'product' => $orderItem->getProduct(),
                'quantity' => (int) $orderItem->getQuantity(),
                'lineTotal' => (float) $orderItem->getTotalPrice(),
            ];
        }

        return $this->render('order/refund.html.twig', [
            'order' => $order,
            'refundItems' => $refundItems,
            'totalItems' => $totalItems,
            'statusLabel' => $checkoutService->getOrderStatusLabel($order->getStatus()),
        ]);
    }
}
