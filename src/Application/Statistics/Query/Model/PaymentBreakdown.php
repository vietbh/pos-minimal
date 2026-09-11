<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class PaymentBreakdown
{
    public function __construct(public string $paymentMethod, public string $amount, public int $paymentCount) {}
}
