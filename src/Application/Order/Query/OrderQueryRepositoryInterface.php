<?php

declare(strict_types=1);

namespace App\Application\Order\Query;

use App\Application\Order\Query\GetDraftOrder\DraftOrderResult;
use App\Application\Order\Query\GetOrder\OrderDetailResult;
use App\Application\Order\Query\ListOrders\ListOrdersInput;
use App\Application\Order\Query\ListOrders\OrderListResult;

interface OrderQueryRepositoryInterface
{
    public function findDraftById(int $orderId): ?DraftOrderResult;

    public function listOrders(ListOrdersInput $input): OrderListResult;

    public function findOrderById(int $orderId): ?OrderDetailResult;
}
