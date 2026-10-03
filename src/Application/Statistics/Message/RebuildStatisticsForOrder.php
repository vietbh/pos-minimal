<?php

declare(strict_types=1);

namespace App\Application\Statistics\Message;

final readonly class RebuildStatisticsForOrder
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $orderId,
    ) {
        if ($eventId === '' || $orderId < 1) {
            throw new \InvalidArgumentException('Statistics message requires an event ID and positive order ID.');
        }
    }
}
