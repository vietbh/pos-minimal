<?php

declare(strict_types=1);
namespace App\Application\Debt\Command\PayDebt;
final readonly class PayDebtHandlerEntryPoint { public function __construct(private PayDebtHandler $handler) {} public function handle(PayDebtInput $input): PayDebtResult { return ($this->handler)($input); } }
