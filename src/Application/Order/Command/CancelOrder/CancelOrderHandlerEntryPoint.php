<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CancelOrder;

final readonly class CancelOrderHandlerEntryPoint
{
    public function __construct(private CancelOrderHandler $handler) {}
    public function handle(CancelOrderInput $input): CancelOrderResult { return ($this->handler)($input); }
}
