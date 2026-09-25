<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Query;

use Doctrine\DBAL\Types\Types;
use App\Application\Statistics\Query\{StatisticsQueryInput,StatisticsQueryRepositoryInterface};
use App\Application\Statistics\Query\Model\{DebtSummary,PaymentBreakdown,SalesSummary,StockSnapshot,TopCustomer,TopProduct};
use Doctrine\DBAL\Connection;

final readonly class StatisticsQueryRepository implements StatisticsQueryRepositoryInterface
{
    public function __construct(private Connection $connection) {}

    public function getSalesSummary(StatisticsQueryInput $input): SalesSummary
    {
        $filter = $this->salesPointFilter($input, 'o');
        $o = $this->connection->fetchAssociative(
            "SELECT
        COALESCE(SUM(CASE WHEN status IN ('COMPLETED','REFUNDED') THEN total ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status='CANCELLED' THEN total ELSE 0 END),0) AS cancelled_amount,
        COUNT(CASE WHEN status='COMPLETED' THEN 1 END) AS completed_orders,
        COUNT(CASE WHEN status='CANCELLED' THEN 1 END) AS cancelled_orders,
        COUNT(CASE WHEN status='REFUNDED' THEN 1 END) AS refunded_orders
     FROM orders o
     WHERE o.created_at >= :from AND created_at < :to
       {$filter['sql']}",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        $refundFilter = $this->salesPointFilter($input, 'o');
        $r = $this->connection->fetchAssociative(
            "SELECT COALESCE(SUM(amount),0) AS refunded_amount
     FROM order_financial_reversals r
     INNER JOIN orders o ON o.id=r.order_id
     WHERE r.type='REFUND' AND r.created_at >= :from AND r.created_at < :to
       {$refundFilter['sql']}",
            $this->params($input, $refundFilter['params']),
            $this->dateRangeTypes($refundFilter['params']),
        );
        $p = $this->connection->fetchAssociative(
            "SELECT
        COALESCE(
            (SELECT SUM(amount)
             FROM payments p
             INNER JOIN orders o ON o.id=p.order_id
             WHERE p.created_at >= :from AND p.created_at < :to
             {$filter['sql']}), 0
        )
        -
        COALESCE(
            (SELECT SUM(amount)
             FROM order_financial_reversals r
             INNER JOIN orders o ON o.id=r.order_id
             WHERE r.created_at >= :from AND r.created_at < :to
             {$filter['sql']}), 0
        ) AS collected",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        $gross = (string)$o['gross_sales'];
        $cancelled = (string)$o['cancelled_amount'];
        $refunded = (string)$r['refunded_amount'];
        $net = $this->decimalSub($gross, $refunded);
        $orders = (int)$o['completed_orders'] + (int)$o['refunded_orders'];
        return new SalesSummary($gross, $net, $this->normalizeDecimal((string)$p['collected']), $cancelled, $refunded, $orders, (int)$o['cancelled_orders'], (int)$o['refunded_orders'], $orders === 0 ? '0.00' : $this->decimalDiv($net, $orders));
    }

    public function getPaymentBreakdown(StatisticsQueryInput $input): array
    {
        $filter = $this->salesPointFilter($input, 'o');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT method AS payment_method,
            COALESCE(SUM(amount),0) AS amount,
            COUNT(*) AS payment_count
     FROM payments p
     INNER JOIN orders o ON o.id=p.order_id
     WHERE p.created_at >= :from AND p.created_at < :to
       {$filter['sql']}
     GROUP BY method
     ORDER BY method ASC",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        return array_map(fn(array $row) => new PaymentBreakdown((string)$row['payment_method'], (string)$row['amount'], (int)$row['payment_count']), $rows);
    }

    public function getDebtSummary(StatisticsQueryInput $input): DebtSummary
    {
        $filter = $this->salesPointFilter($input, 'o');
        $row = $this->connection->fetchAssociative(
            "SELECT COUNT(*) AS debt_count,
            COALESCE(SUM(d.original_amount),0) AS original_amount,
            COALESCE(SUM(COALESCE(dp.paid_amount,0)),0) AS collected_amount,
            COALESCE(
                SUM(
                    CASE
                        WHEN d.status='REVERSED' THEN 0
                        ELSE GREATEST(
                            d.original_amount - COALESCE(dp.paid_amount,0),
                            0
                        )
                    END
                ),
                0
            ) AS outstanding_amount
     FROM debts d
     INNER JOIN orders o ON o.id=d.order_id
     LEFT JOIN (
        SELECT debt_id, SUM(amount) paid_amount
        FROM debt_payments
        GROUP BY debt_id
     ) dp ON dp.debt_id=d.id
     WHERE d.created_at >= :from AND d.created_at < :to
       {$filter['sql']}",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        return new DebtSummary((int)$row['debt_count'],(string)$row['original_amount'],(string)$row['collected_amount'],(string)$row['outstanding_amount']);
    }

    public function getTopProducts(StatisticsQueryInput $input): array
    {
        $filter = $this->salesPointFilter($input, 'o');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT oi.product_id,
            oi.product_name AS name,
            SUM(oi.quantity) quantity,
            COALESCE(SUM(oi.subtotal),0) sales_amount
     FROM order_items oi
     INNER JOIN orders o ON o.id=oi.order_id
     WHERE o.created_at >= :from
       AND o.created_at < :to
       AND o.status='COMPLETED'
       {$filter['sql']}
     GROUP BY oi.product_id, oi.product_name
     ORDER BY quantity DESC, oi.product_id ASC
     LIMIT ".$input->limit,
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        return array_map(fn(array $r)=>new TopProduct((int)$r['product_id'],(string)$r['name'],(int)$r['quantity'],(string)$r['sales_amount']),$rows);
    }

    public function getTopCustomers(StatisticsQueryInput $input): array
    {
        $filter = $this->salesPointFilter($input, 'o');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT o.customer_id,
            c.name,
            COUNT(*) order_count,
            COALESCE(SUM(o.total),0) total_spent
     FROM orders o
     INNER JOIN customers c ON c.id=o.customer_id
     WHERE o.created_at >= :from
       AND o.created_at < :to
       AND o.status='COMPLETED'
       AND o.customer_id IS NOT NULL
       {$filter['sql']}
     GROUP BY o.customer_id, c.name
     ORDER BY total_spent DESC, o.customer_id ASC
     LIMIT ".$input->limit,
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
        return array_map(fn(array $r)=>new TopCustomer((int)$r['customer_id'],(string)$r['name'],(int)$r['order_count'],(string)$r['total_spent']),$rows);
    }

    public function getStockSnapshot(): StockSnapshot
    {
        $row=$this->connection->fetchAssociative("SELECT COUNT(*) total_products, COALESCE(SUM(is_active=1),0) active_products, COALESCE(SUM(is_active=1 AND stock_quantity <= low_stock_threshold AND stock_quantity > 0),0) low_stock_products, COALESCE(SUM(stock_quantity=0),0) out_of_stock_products, COALESCE(SUM(stock_quantity),0) total_stock_quantity FROM products");
        return new StockSnapshot((int)$row['total_products'],(int)$row['active_products'],(int)$row['low_stock_products'],(int)$row['out_of_stock_products'],(int)$row['total_stock_quantity']);
    }

    private function normalizeDecimal(string $value): string {
        $value = trim($value);
        if ($value === '') { return '0.00'; }
        if (!str_contains($value, '.')) { return $value.'.00'; }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        return $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function decimalSub(string $a, string $b): string
    {
        $minor = $this->decimalParts($a) - $this->decimalParts($b);
        if ($minor <= 0) { return $minor === 0 ? '0.00' : '-'.sprintf('%d.%02d', intdiv(abs($minor), 100), abs($minor) % 100); }
        return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }

    private function decimalDiv(string $a, int $n): string
    {
        if ($n <= 0) { return '0.00'; }
        $minor = $this->decimalParts($a);
        $whole = intdiv($minor, $n);
        $remainder = $minor % $n;
        if ($remainder * 2 >= $n) { ++$whole; }
        return sprintf('%d.%02d', intdiv($whole, 100), $whole % 100);
    }

    private function decimalParts(string $v): int
    {
        $v = trim($v);
        $negative = str_starts_with($v, '-');
        $v = ltrim($v, '+-');
        [$w, $f] = array_pad(explode('.', $v, 2), 2, '0');
        $minor = ((int) $w * 100) + (int) str_pad(substr($f, 0, 2), 2, '0');
        return $negative ? -$minor : $minor;
    }

    private function salesPointFilter(StatisticsQueryInput $input, string $alias): array
    {
        if ($input->salesPointId === null) return ['sql' => '', 'params' => []];
        return ['sql' => 'AND '.$alias.'.sales_point_id = :salesPointId', 'params' => ['salesPointId' => $input->salesPointId]];
    }

    private function params(StatisticsQueryInput $input, array $extra = []): array
    { return array_merge($this->dateRangeParams($input), $extra); }

    private function dateRangeParams(StatisticsQueryInput $input): array
    {
        return [
            'from' => $input->from,
            'to' => $input->toExclusive,
        ];
    }

    private function dateRangeTypes(array $extra = []): array
    {
        return array_merge(['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE], $extra ? ['salesPointId' => Types::INTEGER] : []);
    }
}
