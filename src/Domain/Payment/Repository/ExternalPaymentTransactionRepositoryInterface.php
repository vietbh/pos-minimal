<?php
declare(strict_types=1);
namespace App\Domain\Payment\Repository;
use App\Domain\Payment\ExternalPaymentTransaction;
interface ExternalPaymentTransactionRepositoryInterface {
    public function save(ExternalPaymentTransaction $transaction): void;
    public function findByProviderTransactionId(string $provider,string $externalTransactionId): ?ExternalPaymentTransaction;
    public function findLatestByOrderId(int $orderId): ?ExternalPaymentTransaction;
}
