<?php

declare(strict_types=1);

namespace App\Controller;

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
}
