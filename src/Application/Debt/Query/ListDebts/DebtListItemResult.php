<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\ListDebts;
final readonly class DebtListItemResult { public function __construct(public int $id, public int $customerId, public string $customerName, public int $orderId, public string $orderNumber, public string $originalAmount, public string $paidAmount, public string $remainingAmount, public string $status, public \DateTimeImmutable $createdAt) {} }
