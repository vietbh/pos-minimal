<?php

declare(strict_types=1);

namespace App\Infrastructure\Statistics;

use Doctrine\DBAL\Connection;

/** Records a durable statistics event on the caller's current DB transaction. */
final readonly class StatisticsOutboxRecorder
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(string $eventType, int $orderId): void
    {
        if (!in_array($eventType, ['ORDER_COMPLETED', 'ORDER_CANCELLED', 'ORDER_REFUNDED'], true)) {
            throw new \InvalidArgumentException('Unsupported statistics event type.');
        }
        if ($orderId < 1) {
            throw new \InvalidArgumentException('Order ID must be positive.');
        }

        $this->connection->insert('statistics_outbox', [
            'event_id' => self::uuidV4(),
            'event_type' => $eventType,
            'aggregate_id' => $orderId,
            'payload' => json_encode(['orderId' => $orderId], JSON_THROW_ON_ERROR),
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'attempts' => 0,
        ]);
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
