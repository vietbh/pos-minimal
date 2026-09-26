<?php

declare(strict_types=1);

namespace App\Application\Payment\Reference;

use App\Domain\Payment\CheckoutPaymentSession;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\PaymentReference;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Customer\Customer;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Domain\SalesPoint\SalesPoint;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CheckoutPaymentSessionService
{
    public function __construct(
        private CheckoutPaymentSessionRepositoryInterface $sessions,
        private PaymentReferenceRepositoryInterface $references,
        private EntityManagerInterface $em,
        private int $expirationMinutes = 5,
    ) {
        if ($expirationMinutes <= 0) throw new \InvalidArgumentException('Checkout payment session expiration must be greater than zero minutes.');
    }

    /** @param list<array{productId:int,quantity:int,unitPrice:string}> $snapshot */
    public function createOrReuse(
        User $user,
        ?Customer $customer,
        PaymentBankAccount $bankAccount,
        array $snapshot,
        Money $amount,
        int $discountPercent,
        ?string $note,
        string $activeKey,
        ?SalesPoint $salesPoint = null,
        ?Money $manualDiscount = null,
    ): array {
        $existing = $this->sessions->findActiveByKeyForUpdate($activeKey);
        if ($existing !== null) {
            $reference = $this->references->findLatestPendingBySessionForUpdate($existing->getId() ?? 0);
            if ($reference !== null) {
                if ($reference->isExpired()) {
                    $reference->expire();
                    $this->em->flush();
                } else {
                    return $this->toResult($existing, $reference);
                }
            }
            return $this->createReference($existing);
        }

        $session = new CheckoutPaymentSession(
            user: $user,
            customer: $customer,
            bankAccount: $bankAccount,
            cartSnapshot: $snapshot,
            amount: $amount,
            discountPercent: $discountPercent,
            note: $note,
            activeKey: $activeKey,
            expiresAt: new \DateTimeImmutable(sprintf('+%d minutes', $this->expirationMinutes)),
            salesPoint: $salesPoint,
            manualDiscount: $manualDiscount,
        );
        $this->sessions->save($session);
        $this->em->flush();
        return $this->createReference($session);
    }

    /** @return array{sessionId:int,orderId:int|null,reference:string,expiresAt:string,status:string,transferContent:string,qrUrl:string,bankName:string,accountNumber:string,accountName:string,amount:string}|null */
    public function reuseActive(string $activeKey): ?array
    {
        $session = $this->sessions->findActiveByKeyForUpdate($activeKey);
        if ($session === null) return null;
        $reference = $this->references->findLatestPendingBySessionForUpdate($session->getId() ?? 0);
        if ($reference === null || $reference->isExpired()) return null;
        return $this->toResult($session, $reference);
    }

    public function regenerate(int $sessionId): array
    {
        $session = $this->sessions->findByIdForUpdate($sessionId);
        if ($session === null) throw new \DomainException('Checkout payment session not found.');
        if ($session->getStatus()->value !== 'WAITING_FOR_BANK_PAYMENT') throw new \DomainException('Only a waiting payment session can regenerate a reference.');
        $latest = $this->references->findLatestPendingBySessionForUpdate($sessionId);
        if ($latest !== null) {
            if (!$latest->isExpired()) throw new \DomainException('The current payment reference is still valid.');
            $latest->expire();
        }
        return $this->createReference($session);
    }

    /** @return array{sessionId:int,orderId:int|null,reference:string,expiresAt:string,status:string,transferContent:string,qrUrl:string,bankName:string,accountNumber:string,accountName:string,amount:string} */
    private function createReference(CheckoutPaymentSession $session): array
    {
        $reference = new PaymentReference(
            reference: $this->generateUniqueReference(),
            checkoutPaymentSession: $session,
            bankAccount: $session->getBankAccount(),
            amount: $session->getAmount(),
            expiresAt: new \DateTimeImmutable(sprintf('+%d minutes', $this->expirationMinutes)),
        );
        $this->references->save($reference);
        $this->em->flush();
        return $this->toResult($session, $reference);
    }

    private function toResult(CheckoutPaymentSession $session, PaymentReference $reference): array
    {
        $account = $reference->getBankAccount();
        $transferContent = $account->transferContent($reference->getReference());
        $query = http_build_query(['amount' => $reference->getAmount()->toDecimal(), 'addInfo' => $transferContent, 'accountName' => $account->getAccountName()]);
        $qrUrl = sprintf('https://img.vietqr.io/image/%s-%s-%s.png?%s', rawurlencode($account->getBankBin()), rawurlencode($account->getAccountNumber()), rawurlencode($account->getQrTemplate()), $query);
        $subtotal = Money::zero();
        foreach ($session->getCartSnapshot() as $item) {
            $subtotal = $subtotal->add(Money::fromDecimal((string) $item['unitPrice'])->multiply((int) $item['quantity']));
        }
        $discount = $subtotal->subtract($session->getAmount());

        return [
            'sessionId' => $session->getId(),
            'orderId' => $session->getOrder()?->getId(),
            'reference' => $reference->getReference(),
            'expiresAt' => $reference->getExpiresAt()->format(DATE_ATOM),
            'status' => $reference->getStatus()->value,
            'transferContent' => $transferContent,
            'qrUrl' => $qrUrl,
            'bankName' => $account->getBankName(),
            'accountNumber' => $account->getAccountNumber(),
            'accountName' => $account->getAccountName(),
            'subtotal' => $subtotal->toDecimal(),
            'amount' => $reference->getAmount()->toDecimal(),
            'discount' => $discount->toDecimal(),
            'discountPercent' => $session->getDiscountPercent(),
        ];
    }

    private function generateUniqueReference(): string
    {
        do { $reference = strtoupper(bin2hex(random_bytes(6))); }
        while ($this->references->findByReference($reference) !== null);
        return $reference;
    }
}
