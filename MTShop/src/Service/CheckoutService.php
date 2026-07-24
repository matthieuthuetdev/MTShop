<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class CheckoutService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * @param array<int, array{cart: Cart, product: mixed, quantity: int, unitPrice: float, promotion: int, discountedUnitPrice: float, lineTotal: float}> $items
     *
     * @return array{items: array<int, array{cart: Cart, product: mixed, quantity: int, unitPrice: float, promotion: int, discountedUnitPrice: float, lineTotal: float}>, totalItems: int, subtotal: float}
     */
    public function summarizeCart(iterable $carts): array
    {
        $items = [];
        $totalItems = 0;
        $subtotal = 0.0;

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
            $subtotal += $lineTotal;
        }

        return [
            'items' => $items,
            'totalItems' => $totalItems,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * @return array<string, array{code: string, label: string, description: string, note: string, fee: float, available: bool, estimate: string}>
     */
    public function getDeliveryOptions(float $subtotal, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $hour = (int) $now->format('G');

        $basicFee = $subtotal > 50.0 ? 0.0 : 5.0;

        return [
            'express' => [
                'code' => 'express',
                'label' => 'Express max',
                'description' => 'Livraison en 1h, 20 €.',
                'note' => 'Si commande passée avant 15h, livraison entre 18h et 20h le même jour. Offre soumise à condition.',
                'fee' => 20.0,
                'available' => $hour < 15,
                'estimate' => $hour < 15 ? 'Aujourd’hui entre 18h et 20h' : 'Disponible uniquement avant 15h',
            ],
            'next_day' => [
                'code' => 'next_day',
                'label' => 'Livraison le lendemain',
                'description' => 'Si la commande est passée avant midi, livraison le lendemain, 10 €.',
                'note' => 'Disponible uniquement si la commande est passée avant 12h.',
                'fee' => 10.0,
                'available' => $hour < 12,
                'estimate' => $hour < 12 ? 'Demain' : 'Disponible uniquement avant 12h',
            ],
            'basic' => [
                'code' => 'basic',
                'label' => 'Basic',
                'description' => 'Livraison en 3 à 4 jours ouvrés. Offerte si le panier dépasse 50 €, sinon 5 €.',
                'note' => 'Livraison standard.',
                'fee' => $basicFee,
                'available' => true,
                'estimate' => $this->buildBusinessDaysEstimate($now, 3, 4),
            ],
        ];
    }

    public function resolveDeliveryOption(string $code, float $subtotal, ?DateTimeImmutable $now = null): ?array
    {
        $options = $this->getDeliveryOptions($subtotal, $now);

        return $options[$code] ?? null;
    }

    /**
     * @param array<int, array{cart: Cart, product: mixed, quantity: int, unitPrice: float, promotion: int, discountedUnitPrice: float, lineTotal: float}> $items
     */
    public function createOrder(User $customer, array $items, float $subtotal, array $deliveryOption): Order
    {
        $order = new Order();
        $order->setCustomer($customer);
        $order->setStatus('confirmed');
        $order->setPaymentStatus('not_available');
        $order->setPaymentMethod('Non disponible');
        $order->setDeliveryMethod($deliveryOption['code']);
        $order->setDeliveryFee(number_format((float) $deliveryOption['fee'], 2, '.', ''));
        $order->setShippingAddress((string) ($customer->getShippingAddress() ?? ''));
        $order->setBillingAddress((string) ($customer->getBillingAddress() ?? ''));
        $order->setTotalAmount(number_format($subtotal + (float) $deliveryOption['fee'], 2, '.', ''));

        foreach ($items as $item) {
            $product = $item['product'];
            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity((int) $item['quantity']);
            $orderItem->setUnitPrice(number_format((float) $item['discountedUnitPrice'], 2, '.', ''));
            $orderItem->setTotalPrice(number_format((float) $item['lineTotal'], 2, '.', ''));
            $order->addOrderItem($orderItem);
        }

        return $order;
    }

    public function sendOrderNotification(Order $order, array $summary, array $deliveryOption): void
    {
        $recipient = $this->extractRecipientAddress($this->mailerFrom);
        $customer = $order->getCustomer();
        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($recipient)
            ->subject('Nouvelle commande MTShop')
            ->htmlTemplate('mail/order_notification.html.twig')
            ->context([
                'order' => $order,
                'customer' => $customer,
                'items' => $summary['items'],
                'subtotal' => $summary['subtotal'],
                'deliveryOption' => $deliveryOption,
                'grandTotal' => (float) $order->getTotalAmount(),
            ]);

        $this->mailer->send($email);
    }

    public function getDeliveryDateText(Order $order): string
    {
        $createdAt = $order->getCreatedAt() ?? new DateTimeImmutable();
        $method = $order->getDeliveryMethod() ?? 'basic';

        return match ($method) {
            'express' => $createdAt->format('d/m/Y') . ' entre 18h et 20h',
            'next_day' => $createdAt->modify('+1 day')->format('d/m/Y'),
            default => $this->buildBusinessDaysEstimate($createdAt, 3, 4),
        };
    }

    private function extractRecipientAddress(string $value): Address
    {
        if (preg_match('/<([^>]+)>/', $value, $matches) === 1) {
            return new Address(trim($matches[1]));
        }

        return new Address(trim($value));
    }

    private function buildBusinessDaysEstimate(DateTimeImmutable $startDate, int $minDays, int $maxDays): string
    {
        $minDate = $this->addBusinessDays($startDate, $minDays);
        $maxDate = $this->addBusinessDays($startDate, $maxDays);

        return sprintf('Entre le %s et le %s', $minDate->format('d/m/Y'), $maxDate->format('d/m/Y'));
    }

    private function addBusinessDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        $currentDate = $date;
        $remainingDays = $days;

        while ($remainingDays > 0) {
            $currentDate = $currentDate->add(new DateInterval('P1D'));
            $weekday = (int) $currentDate->format('N');

            if ($weekday < 6) {
                --$remainingDays;
            }
        }

        return $currentDate;
    }
}
