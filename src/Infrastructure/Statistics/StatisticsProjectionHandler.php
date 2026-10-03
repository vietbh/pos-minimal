<?php

declare(strict_types=1);

namespace App\Infrastructure\Statistics;

use App\Application\Statistics\Message\RebuildStatisticsForOrder;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StatisticsProjectionHandler
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private string $appTimezone = 'UTC',
        private string $appPublicUrl = 'http://localhost',
    ) {
    }

    public function __invoke(RebuildStatisticsForOrder $message): void
    {
        $this->connection->beginTransaction();
        try {
            // Claim the event in this transaction. A concurrent duplicate blocks on
            // the primary key and then observes zero affected rows after the first
            // transaction commits. If projection work fails, this claim rolls back.
            $claimed = $this->connection->executeStatement(
                'INSERT IGNORE INTO statistics_processed_events (event_id, order_id, event_type, processed_at) VALUES (?, ?, ?, ?)',
                [$message->eventId, $message->orderId, $message->eventType, (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            );
            if ($claimed === 0) {
                $this->connection->commit();
                return;
            }

            $order = $this->connection->fetchAssociative(
                'SELECT id, status, completed_at, created_at, sales_point_id FROM orders WHERE id = ?',
                [$message->orderId],
            );
            if ($order === false) {
                throw new \RuntimeException(sprintf('Order %d referenced by statistics event does not exist.', $message->orderId));
            }

            // Statistics use completion date, not draft creation date. Dates in the
            // database are naive timestamps interpreted in APP_TIMEZONE, consistent
            // with the rest of this application.
            $completedAt = $order['completed_at'] ?: null;
            if ($completedAt !== null) {
                $date = new \DateTimeImmutable((string) $completedAt, new \DateTimeZone($this->appTimezone));
                $businessDate = $date->format('Y-m-d');
                $start = $date->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                $end = $date->setTime(0, 0, 0)->modify('+1 day')->format('Y-m-d H:i:s');
                $salesPointId = $order['sales_point_id'] === null ? 0 : (int) $order['sales_point_id'];
                $scopes = array_values(array_unique([0, $salesPointId]));
                sort($scopes, SORT_NUMERIC);

                foreach ($scopes as $scopeId) {
                    $this->rebuildScope($businessDate, $start, $end, $scopeId);
                    $this->connection->insert('statistics_notification_outbox', [
                        'event_id' => $message->eventId,
                        'scope_id' => $scopeId,
                        'topic' => $this->topicForScope($scopeId),
                        'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'attempts' => 0,
                    ], ['event_id' => \Doctrine\DBAL\ParameterType::STRING]);
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->logger->error('Statistics projection processing failed.', [
                'event_id' => $message->eventId,
                'event_type' => $message->eventType,
                'order_id' => $message->orderId,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    private function rebuildScope(string $date, string $start, string $end, int $scopeId): void
    {
        $lockKey = $date . ':' . $scopeId;
        $this->connection->executeStatement(
            'INSERT IGNORE INTO statistics_projection_locks (lock_key, lock_version) VALUES (?, 0)',
            [$lockKey],
        );
        $this->connection->fetchOne('SELECT lock_key FROM statistics_projection_locks WHERE lock_key = ? FOR UPDATE', [$lockKey]);

        $scopeSql = $scopeId === 0 ? '' : ' AND o.sales_point_id = :scopeFilter';
        $params = ['start' => $start, 'end' => $end];
        if ($scopeId !== 0) {
            $params['scopeFilter'] = $scopeId;
        }

        $this->connection->delete('daily_product_sales_statistics', ['business_date' => $date, 'sales_point_id' => $scopeId]);
        $this->connection->delete('daily_customer_sales_statistics', ['business_date' => $date, 'sales_point_id' => $scopeId]);
        $this->connection->delete('daily_sales_statistics', ['business_date' => $date, 'sales_point_id' => $scopeId]);

        $this->connection->executeStatement(
            "INSERT INTO daily_sales_statistics (business_date, sales_point_id, completed_order_count, gross_sales, discount_amount, net_sales, updated_at)
             SELECT :businessDate, :scopeInsert, COUNT(*), COALESCE(SUM(o.subtotal), 0), COALESCE(SUM(o.discount), 0), COALESCE(SUM(o.total), 0), NOW()
             FROM orders o WHERE o.status = 'COMPLETED' AND o.completed_at >= :start AND o.completed_at < :end {$scopeSql}",
            ['businessDate' => $date, 'scopeInsert' => $scopeId] + $params,
        );

        $this->connection->executeStatement(
            "INSERT INTO daily_product_sales_statistics (business_date, sales_point_id, product_id, product_name_snapshot, quantity_sold, gross_sales, completed_order_count, updated_at)
             SELECT :businessDate, :scopeInsert, oi.product_id, MAX(oi.product_name), SUM(oi.quantity), COALESCE(SUM(oi.subtotal), 0), COUNT(DISTINCT o.id), NOW()
             FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.status = 'COMPLETED' AND o.completed_at >= :start AND o.completed_at < :end {$scopeSql}
             GROUP BY oi.product_id",
            ['businessDate' => $date, 'scopeInsert' => $scopeId] + $params,
        );

        $this->connection->executeStatement(
            "INSERT INTO daily_customer_sales_statistics (business_date, sales_point_id, customer_id, order_count, gross_spend, discount_amount, net_spend, last_order_at, updated_at)
             SELECT :businessDate, :scopeInsert, o.customer_id, COUNT(*), COALESCE(SUM(o.subtotal), 0), COALESCE(SUM(o.discount), 0), COALESCE(SUM(o.total), 0), MAX(o.completed_at), NOW()
             FROM orders o WHERE o.status = 'COMPLETED' AND o.customer_id IS NOT NULL AND o.completed_at >= :start AND o.completed_at < :end {$scopeSql}
             GROUP BY o.customer_id",
            ['businessDate' => $date, 'scopeInsert' => $scopeId] + $params,
        );
    }

    private function topicForScope(int $scopeId): string
    {
        return rtrim($this->appPublicUrl, '/') . '/statistics/' . $scopeId;
    }
}
