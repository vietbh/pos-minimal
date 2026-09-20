<?php
declare(strict_types=1);
namespace App\Domain\Payment\Repository;
use App\Domain\Payment\PaymentBankAccount;
interface PaymentBankAccountRepositoryInterface {
    public function save(PaymentBankAccount $account): void;
    public function findById(int $id): ?PaymentBankAccount;
    /** @return list<PaymentBankAccount> */ public function findAll(): array;
    /** @return list<PaymentBankAccount> */ public function findActive(): array;
}
