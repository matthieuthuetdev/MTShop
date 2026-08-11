<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SELLER')]
final class SellerOrderController extends AbstractController
{
    #[Route('/seller/orders', name: 'app_seller_orders_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, CheckoutService $checkoutService): Response
    {
        $orders = $orderRepository->findProcessableOrdersForSeller();
        $orderCards = [];

        foreach ($orders as $order) {
            $totalItems = 0;
            foreach ($order->getOrderItems() as $orderItem) {
                $totalItems += (int) $orderItem->getQuantity();
            }

            $orderCards[] = [
                'order' => $order,
                'totalItems' => $totalItems,
                'statusLabel' => $checkoutService->getOrderStatusLabel($order->getStatus()),
                'canShip' => $checkoutService->canSellerMarkAsShipped($order),
                'canDeliver' => $checkoutService->canSellerMarkAsDelivered($order),
                'canCancel' => $checkoutService->canSellerMarkAsCancelled($order),
            ];
        }

        return $this->render('seller/orders.html.twig', [
            'orderCards' => $orderCards,
            'redirectPath' => $this->generateUrl('app_seller_orders_index'),
        ]);
    }

    #[Route('/seller/orders/{id}', name: 'app_seller_order_show', methods: ['GET'])]
    public function show(Order $order, CheckoutService $checkoutService): Response
    {
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

        return $this->render('seller/order_show.html.twig', [
            'order' => $order,
            'orderItems' => $orderItems,
            'totalItems' => $totalItems,
            'statusLabel' => $checkoutService->getOrderStatusLabel($order->getStatus()),
            'deliveryDateText' => $checkoutService->getDeliveryDateText($order),
            'canShip' => $checkoutService->canSellerMarkAsShipped($order),
            'canDeliver' => $checkoutService->canSellerMarkAsDelivered($order),
            'canCancel' => $checkoutService->canSellerMarkAsCancelled($order),
            'redirectPath' => $this->generateUrl('app_seller_order_show', ['id' => $order->getId()]),
        ]);
    }

    #[Route('/seller/orders/{id}/status', name: 'app_seller_order_update_status', methods: ['POST'])]
    public function updateStatus(
        Order $order,
        Request $request,
        EntityManagerInterface $entityManager,
        CheckoutService $checkoutService,
    ): Response {
        if (!$this->isCsrfTokenValid('seller_order_status_'.$order->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

            return $this->redirectToSafePath((string) $request->request->get('redirect_to', ''), 'app_seller_orders_index');
        }

        $targetStatus = (string) $request->request->get('status', '');
        $reason = trim((string) $request->request->get('reason', ''));

        $allowed = match ($targetStatus) {
            'shipped' => $checkoutService->canSellerMarkAsShipped($order),
            'delivered' => $checkoutService->canSellerMarkAsDelivered($order),
            'cancelled' => $checkoutService->canSellerMarkAsCancelled($order),
            default => false,
        };

        if (!$allowed) {
            $this->addFlash('warning', 'Cette action n\'est pas disponible pour cette commande.');

            return $this->redirectToSafePath((string) $request->request->get('redirect_to', ''), 'app_seller_orders_index');
        }

        if ('cancelled' === $targetStatus && '' === $reason) {
            $this->addFlash('warning', 'Veuillez renseigner une raison pour l\'annulation.');

            return $this->redirectToSafePath((string) $request->request->get('redirect_to', ''), 'app_seller_orders_index');
        }

        $order->setStatus($targetStatus);
        $entityManager->flush();

        try {
            $checkoutService->sendOrderStatusUpdateToCustomer($order, 'cancelled' === $targetStatus ? $reason : null);
        } catch (\Throwable $exception) {
            $this->addFlash('warning', 'Le statut a été mis à jour, mais l\'e-mail client n\'a pas pu être envoyé.');

            return $this->redirectToSafePath((string) $request->request->get('redirect_to', ''), 'app_seller_orders_index');
        }

        $this->addFlash('success', sprintf('La commande #%d a bien été mise à jour : %s.', $order->getId(), $checkoutService->getOrderStatusLabel($targetStatus)));

        return $this->redirectToSafePath((string) $request->request->get('redirect_to', ''), 'app_seller_orders_index');
    }

    private function redirectToSafePath(string $path, string $fallbackRoute): Response
    {
        if ('' !== $path && str_starts_with($path, '/')) {
            return $this->redirect($path);
        }

        return $this->redirectToRoute($fallbackRoute);
    }
}
