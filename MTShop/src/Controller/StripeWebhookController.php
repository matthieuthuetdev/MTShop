<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use App\Service\CheckoutService;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        StripeCheckoutService $stripeCheckoutService,
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        CheckoutService $checkoutService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ): Response {
        $payload = (string) $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            $event = $stripeCheckoutService->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException|\UnexpectedValueException|\RuntimeException $exception) {
            $logger->warning('Rejected Stripe webhook.', [
                'error' => $exception->getMessage(),
            ]);

            return new Response('Invalid webhook', Response::HTTP_BAD_REQUEST);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                /** @var object $session */
                $session = $event->data->object;
                $sessionId = (string) ($session->id ?? '');
                $paymentIntentId = isset($session->payment_intent) ? (string) $session->payment_intent : null;
                $order = $orderRepository->findOneByStripeCheckoutSessionId($sessionId);

                if (null === $order) {
                    $logger->error('Stripe webhook received for unknown checkout session.', [
                        'session_id' => $sessionId,
                    ]);

                    return new Response('Order not found', Response::HTTP_NOT_FOUND);
                }

                if ('paid' === $order->getPaymentStatus()) {
                    return new Response('Already processed', Response::HTTP_OK);
                }

                $checkoutService->markOrderAsPaid($order, $cartRepository);
                $order->setStripePaymentIntentId($paymentIntentId);

                foreach ($order->getOrderItems() as $orderItem) {
                    $product = $orderItem->getProduct();
                    if (null === $product) {
                        continue;
                    }

                    $cart = $cartRepository->findOneBy([
                        'customer' => $order->getCustomer(),
                        'product' => $product,
                    ]);

                    if ($cart instanceof Cart && (int) $cart->getQuantity() <= 0) {
                        $entityManager->remove($cart);
                    }
                }

                $entityManager->flush();

                try {
                    $checkoutService->sendPaidOrderNotification($order);
                } catch (\Throwable $exception) {
                    $logger->error('Paid order notification failed.', [
                        'order_id' => $order->getId(),
                        'error' => $exception->getMessage(),
                    ]);
                }

                return new Response('Payment processed', Response::HTTP_OK);

            case 'checkout.session.expired':
                /** @var object $session */
                $session = $event->data->object;
                $sessionId = (string) ($session->id ?? '');
                $order = $orderRepository->findOneByStripeCheckoutSessionId($sessionId);

                if ($order && 'pending' === $order->getPaymentStatus()) {
                    $checkoutService->markOrderAsPaymentCancelled($order);
                    $entityManager->flush();
                }

                return new Response('Session expired handled', Response::HTTP_OK);

            default:
                return new Response('Event ignored', Response::HTTP_OK);
        }
    }
}
