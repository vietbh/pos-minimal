<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:statistics:rebuild-projections', description: 'Rebuild all daily statistics projections from committed source-of-truth orders.')]
final class RebuildStatisticsProjectionsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>Rebuilding projections. Pause Messenger workers/outbox relays during this maintenance operation.</comment>');
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('DELETE FROM daily_customer_sales_statistics');
            $this->connection->executeStatement('DELETE FROM daily_product_sales_statistics');
            $this->connection->executeStatement('DELETE FROM daily_sales_statistics');

            // Global scope (0) includes orders with and without an assigned sales point.
            $this->connection->executeStatement("INSERT INTO daily_sales_statistics (business_date, sales_point_id, completed_order_count, gross_sales, discount_amount, net_sales, updated_at)
                SELECT DATE(completed_at), 0, COUNT(*), COALESCE(SUM(subtotal),0), COALESCE(SUM(discount),0), COALESCE(SUM(total),0), NOW()
                FROM orders WHERE status='COMPLETED' AND completed_at IS NOT NULL GROUP BY DATE(completed_at)");
            $this->connection->executeStatement("INSERT INTO daily_sales_statistics (business_date, sales_point_id, completed_order_count, gross_sales, discount_amount, net_sales, updated_at)
                SELECT DATE(completed_at), sales_point_id, COUNT(*), COALESCE(SUM(subtotal),0), COALESCE(SUM(discount),0), COALESCE(SUM(total),0), NOW()
                FROM orders WHERE status='COMPLETED' AND completed_at IS NOT NULL AND sales_point_id IS NOT NULL GROUP BY DATE(completed_at), sales_point_id");

            $this->connection->executeStatement("INSERT INTO daily_product_sales_statistics (business_date, sales_point_id, product_id, product_name_snapshot, quantity_sold, gross_sales, completed_order_count, updated_at)
                SELECT DATE(o.completed_at), 0, oi.product_id, MAX(oi.product_name), SUM(oi.quantity), COALESCE(SUM(oi.subtotal),0), COUNT(DISTINCT o.id), NOW()
                FROM orders o INNER JOIN order_items oi ON oi.order_id=o.id
                WHERE o.status='COMPLETED' AND o.completed_at IS NOT NULL GROUP BY DATE(o.completed_at), oi.product_id");
            $this->connection->executeStatement("INSERT INTO daily_product_sales_statistics (business_date, sales_point_id, product_id, product_name_snapshot, quantity_sold, gross_sales, completed_order_count, updated_at)
                SELECT DATE(o.completed_at), o.sales_point_id, oi.product_id, MAX(oi.product_name), SUM(oi.quantity), COALESCE(SUM(oi.subtotal),0), COUNT(DISTINCT o.id), NOW()
                FROM orders o INNER JOIN order_items oi ON oi.order_id=o.id
                WHERE o.status='COMPLETED' AND o.completed_at IS NOT NULL AND o.sales_point_id IS NOT NULL GROUP BY DATE(o.completed_at), o.sales_point_id, oi.product_id");

            $this->connection->executeStatement("INSERT INTO daily_customer_sales_statistics (business_date, sales_point_id, customer_id, order_count, gross_spend, discount_amount, net_spend, last_order_at, updated_at)
                SELECT DATE(completed_at), 0, customer_id, COUNT(*), COALESCE(SUM(subtotal),0), COALESCE(SUM(discount),0), COALESCE(SUM(total),0), MAX(completed_at), NOW()
                FROM orders WHERE status='COMPLETED' AND completed_at IS NOT NULL AND customer_id IS NOT NULL GROUP BY DATE(completed_at), customer_id");
            $this->connection->executeStatement("INSERT INTO daily_customer_sales_statistics (business_date, sales_point_id, customer_id, order_count, gross_spend, discount_amount, net_spend, last_order_at, updated_at)
                SELECT DATE(completed_at), sales_point_id, customer_id, COUNT(*), COALESCE(SUM(subtotal),0), COALESCE(SUM(discount),0), COALESCE(SUM(total),0), MAX(completed_at), NOW()
                FROM orders WHERE status='COMPLETED' AND completed_at IS NOT NULL AND sales_point_id IS NOT NULL AND customer_id IS NOT NULL GROUP BY DATE(completed_at), sales_point_id, customer_id");

            $this->connection->commit();
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) $this->connection->rollBack();
            throw $e;
        }

        $salesDays = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM daily_sales_statistics');
        $products = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM daily_product_sales_statistics');
        $customers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM daily_customer_sales_statistics');
        $output->writeln(sprintf('Rebuild complete: %d daily sales rows, %d product rows, %d customer rows.', $salesDays, $products, $customers));
        return Command::SUCCESS;
    }
}
