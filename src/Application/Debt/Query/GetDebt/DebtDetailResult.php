<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\GetDebt;
final readonly class DebtDetailResult { /** @param list<DebtPaymentResult> $payments */ public function __construct(public int $id, public int $customerId, public string $customerName, public ?string $customerPhone, public int $orderId, public string $orderNumber, public string $originalAmount, public string $paidAmount, public string $remainingAmount, public string $status, public \DateTimeImmutable $createdAt, public array $payments) {} public function canPay(): bool { return in_array($this->status, ['OPEN','PARTIALLY_PAID'], true) && $this->remainingAmount !== '0.00'; } }
