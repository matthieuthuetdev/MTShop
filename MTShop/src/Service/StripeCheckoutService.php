<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeCheckoutService
{
    private readonly StripeClient $stripeClient;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%env(STRIPE_PUBLISHABLE_KEY)%')]
        private readonly string $publishableKey,
        #[Autowire('%env(default::STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {
        $this->stripeClient = new StripeClient($this->secretKey);
    }

    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        return $this->stripeClient->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_method_types' => ['card'],
            'customer_email' => $order->getCustomer()?->getEmail(),
            'metadata' => [
                'order_id' => (string) $order->getId(),
            ],
            'line_items' => $this->buildLineItems($order),
        ]);
    }

    /**
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, ?string $signature): Event
    {
        if ('' === $this->webhookSecret) {
            throw new \RuntimeException('The Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, (string) $signature, $this->webhookSecret);
    }

    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLineItems(Order $order): array
    {
        $lineItems = [];

        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $productName = $product?->getName() ?? sprintf('Produit #%d', $orderItem->getId());
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $productName,
                    ],
                    'unit_amount' => (int) round(((float) $orderItem->getUnitPrice()) * 100),
                ],
                'quantity' => (int) $orderItem->getQuantity(),
            ];
        }

        $deliveryFee = (float) ($order->getDeliveryFee() ?? '0');
        if ($deliveryFee > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Frais de livraison',
                    ],
                    'unit_amount' => (int) round($deliveryFee * 100),
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }
}
