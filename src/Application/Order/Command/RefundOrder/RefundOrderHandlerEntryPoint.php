<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RefundOrder;

final readonly class RefundOrderHandlerEntryPoint
{
    public function __construct(private RefundOrderHandler $handler) {}
    public function handle(RefundOrderInput $input): RefundOrderResult { return ($this->handler)($input); }
}
