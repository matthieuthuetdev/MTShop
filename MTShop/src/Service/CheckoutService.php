<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class CheckoutService
{
    private const NON_CANCELLABLE_STATUSES = ['shipped', 'delivered', 'cancelled'];
    private const SELLER_SHIPPABLE_STATUSES = ['pending', 'confirmed', 'preparing'];
    private const SELLER_DELIVERABLE_STATUSES = ['shipped'];
    private const SELLER_CANCELLABLE_STATUSES = ['pending', 'confirmed', 'preparing', 'shipped'];

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

        $expressAvailable = $hour < 15;
        $nextDayAvailable = $hour < 12;
        $basicFee = $subtotal > 50.0 ? 0.0 : 5.0;

        return [
            'express' => [
                'code' => 'express',
                'label' => 'Express max',
                'description' => 'Livraison le jour même entre 18h et 20h, 20 €.',
                'note' => 'Si commande passée avant 15h, livraison entre 18h et 20h le même jour. Offre soumise à condition.',
                'fee' => 20.0,
                'available' => $expressAvailable,
                'estimate' => $expressAvailable ? 'Aujourd’hui entre 18h et 20h' : 'Non disponible',
            ],
            'next_day' => [
                'code' => 'next_day',
                'label' => 'Livraison le lendemain',
                'description' => 'Si la commande est passée avant midi, livraison le lendemain, 10 €.',
                'note' => 'Disponible uniquement si la commande est passée avant 12h.',
                'fee' => 10.0,
                'available' => $nextDayAvailable,
                'estimate' => $nextDayAvailable ? 'Demain' : 'Non disponible',
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
    public function createPendingOrder(User $customer, array $items, float $subtotal, array $deliveryOption): Order
    {
        $order = new Order();
        $order->setCustomer($customer);
        $order->setStatus('pending');
        $order->setPaymentStatus('pending');
        $order->setPaymentMethod('Stripe Checkout');
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

    public function markOrderAsPaid(Order $order, CartRepository $cartRepository): void
    {
        if ('paid' === $order->getPaymentStatus()) {
            return;
        }

        $order->setPaymentStatus('paid');
        $order->setStatus('confirmed');
        $order->setPaymentMethod('Stripe Checkout');

        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();
            if (!$product instanceof Product) {
                continue;
            }

            $product->setOrderCount((int) $product->getOrderCount() + (int) $orderItem->getQuantity());
            $product->setStock(max(0, (int) $product->getStock() - (int) $orderItem->getQuantity()));

            $cart = $cartRepository->findOneBy([
                'customer' => $order->getCustomer(),
                'product' => $product,
            ]);

            if (!$cart instanceof Cart) {
                continue;
            }

            $remainingQuantity = (int) $cart->getQuantity() - (int) $orderItem->getQuantity();
            if ($remainingQuantity > 0) {
                $cart->setQuantity($remainingQuantity);
                continue;
            }

            $cart->setQuantity(0);
        }
    }

    public function markOrderAsPaymentCancelled(Order $order): void
    {
        if ('paid' === $order->getPaymentStatus()) {
            return;
        }

        $order->setPaymentStatus('cancelled');
        $order->setStatus('cancelled');
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

    public function sendPaidOrderNotification(Order $order): void
    {
        $recipient = $this->extractRecipientAddress($this->mailerFrom);
        $customer = $order->getCustomer();
        $subtotal = $this->getOrderSubtotal($order);
        $deliveryOption = $this->getDeliveryOptionForOrder($order, $subtotal);
        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($recipient)
            ->subject('Nouvelle commande MTShop')
            ->htmlTemplate('mail/order_notification.html.twig')
            ->context([
                'order' => $order,
                'customer' => $customer,
                'items' => $this->buildNotificationItemsFromOrder($order),
                'subtotal' => $subtotal,
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

    public function getOrderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'preparing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            default => 'Inconnue',
        };
    }

    public function canCancelOrder(Order $order): bool
    {
        return !in_array($order->getStatus(), self::NON_CANCELLABLE_STATUSES, true);
    }

    public function canRequestRefund(Order $order): bool
    {
        return 'delivered' === $order->getStatus();
    }

    public function canSellerMarkAsShipped(Order $order): bool
    {
        return in_array($order->getStatus(), self::SELLER_SHIPPABLE_STATUSES, true);
    }

    public function canSellerMarkAsDelivered(Order $order): bool
    {
        return in_array($order->getStatus(), self::SELLER_DELIVERABLE_STATUSES, true);
    }

    public function canSellerMarkAsCancelled(Order $order): bool
    {
        return in_array($order->getStatus(), self::SELLER_CANCELLABLE_STATUSES, true);
    }

    public function isProductAvailableForReorder(?Product $product): bool
    {
        if (!$product instanceof Product) {
            return false;
        }

        return $product->isActive() === true && (int) $product->getStock() > 0;
    }

    /**
     * @return array<int, array{product: Product, quantity: int, reason: string}>
     */
    public function buildRefundSelection(Order $order, array $selectedItems, array $reasons): array
    {
        $selection = [];

        foreach ($order->getOrderItems() as $orderItem) {
            $itemId = (string) $orderItem->getId();
            if (!array_key_exists($itemId, $selectedItems)) {
                continue;
            }

            $reason = trim((string) ($reasons[$itemId] ?? ''));
            $product = $orderItem->getProduct();

            if (!$product instanceof Product || '' === $reason) {
                continue;
            }

            $selection[] = [
                'product' => $product,
                'quantity' => (int) $orderItem->getQuantity(),
                'reason' => $reason,
            ];
        }

        return $selection;
    }

    /**
     * @param array<int, array{product: Product, quantity: int, reason: string}> $selectedItems
     */
    public function sendRefundRequestNotification(Order $order, array $selectedItems): void
    {
        $recipient = $this->extractRecipientAddress($this->mailerFrom);
        $customer = $order->getCustomer();
        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($recipient)
            ->subject(sprintf('Demande de remboursement MTShop pour la commande #%d', $order->getId()))
            ->htmlTemplate('mail/refund_request.html.twig')
            ->context([
                'order' => $order,
                'customer' => $customer,
                'selectedItems' => $selectedItems,
            ]);

        $this->mailer->send($email);
    }

    public function sendOrderStatusUpdateToCustomer(Order $order, ?string $reason = null): void
    {
        $customer = $order->getCustomer();
        if (!$customer instanceof User || null === $customer->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to(new Address($customer->getEmail(), trim(sprintf('%s %s', (string) $customer->getFirstName(), (string) $customer->getLastName()))))
            ->subject(sprintf('Mise à jour de votre commande MTShop #%d', $order->getId()))
            ->htmlTemplate('mail/order_status_update.html.twig')
            ->context([
                'order' => $order,
                'customer' => $customer,
                'statusLabel' => $this->getOrderStatusLabel($order->getStatus()),
                'reason' => $reason,
            ]);

        $this->mailer->send($email);
    }

    public function getOrderSubtotal(Order $order): float
    {
        return (float) $order->getTotalAmount() - (float) ($order->getDeliveryFee() ?? '0');
    }

    /**
     * @return array{code: string, label: string, description: string, note: string, fee: float, available: bool, estimate: string}
     */
    public function getDeliveryOptionForOrder(Order $order, ?float $subtotal = null): array
    {
        $subtotal ??= $this->getOrderSubtotal($order);
        $deliveryOptions = $this->getDeliveryOptions($subtotal, $order->getCreatedAt());

        return $deliveryOptions[$order->getDeliveryMethod() ?? 'basic'] ?? $deliveryOptions['basic'];
    }

    /**
     * @return list<array{product: Product|null, quantity: int, discountedUnitPrice: float, lineTotal: float}>
     */
    public function buildNotificationItemsFromOrder(Order $order): array
    {
        $items = [];

        foreach ($order->getOrderItems() as $orderItem) {
            $items[] = [
                'product' => $orderItem->getProduct(),
                'quantity' => (int) $orderItem->getQuantity(),
                'discountedUnitPrice' => (float) $orderItem->getUnitPrice(),
                'lineTotal' => (float) $orderItem->getTotalPrice(),
            ];
        }

        return $items;
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
