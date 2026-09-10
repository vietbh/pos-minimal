<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\GetDebt;
final readonly class DebtPaymentResult { public function __construct(public int $id, public string $amount, public string $username, public \DateTimeImmutable $createdAt) {} }
