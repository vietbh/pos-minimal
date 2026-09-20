<?php

declare(strict_types=1);

namespace App\Application\Payment\Reference;

use App\Domain\Order\Order;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;

/**
 * Compatibility facade for the legacy order-based regenerate endpoint.
 * New bank-transfer checkouts are session-first and use CheckoutPaymentSessionService.
 */
final readonly class PaymentReferenceService
{
    public function __construct(
        private CheckoutPaymentSessionService $sessions,
        private CheckoutPaymentSessionRepositoryInterface $sessionRepository,
        private PaymentReferenceRepositoryInterface $references,
    ) {}

    public function regenerateForOrder(int $orderId): array
    {
        $reference = $this->references->findLatestByOrderIdForUpdate($orderId);
        if ($reference === null) {
            throw new \DomainException('No bank payment reference exists for this order.');
        }
        $session = $reference->getCheckoutPaymentSession();
        if ($session->getId() === null) {
            throw new \LogicException('Checkout payment session has no database identity.');
        }
        return $this->sessions->regenerate($session->getId());
    }
}
