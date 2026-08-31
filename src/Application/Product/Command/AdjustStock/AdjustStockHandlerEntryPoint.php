<?php

declare(strict_types=1);

namespace App\Application\Product\Command\AdjustStock;

final readonly class AdjustStockHandlerEntryPoint
{
    public function __construct(
        private AdjustStockHandler $handler,
    ) {
    }

    public function handle(AdjustStockInput $input): AdjustStockResult
    {
        return ($this->handler)($input);
    }
}
