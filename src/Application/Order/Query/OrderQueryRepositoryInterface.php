<?php

declare(strict_types=1);

namespace App\Application\Order\Query;

use App\Application\Order\Query\GetDraftOrder\DraftOrderResult;

interface OrderQueryRepositoryInterface
{
    public function findDraftById(int $orderId): ?DraftOrderResult;
}
