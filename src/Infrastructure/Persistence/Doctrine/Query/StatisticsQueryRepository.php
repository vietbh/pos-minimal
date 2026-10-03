<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Query;

use Doctrine\DBAL\Types\Types;
use App\Application\Statistics\Query\{StatisticsQueryInput,StatisticsQueryRepositoryInterface};
use App\Application\Statistics\Query\Model\{DebtSummary,PaymentBreakdown,SalesSummary,StockSnapshot,TopCustomer,TopProduct,WeeklySalesPoint,WeeklySalesSummary};
use Doctrine\DBAL\Connection;

final readonly class StatisticsQueryRepository implements StatisticsQueryRepositoryInterface
{
    public function __construct(private Connection $connection, private bool $useStatisticsProjections = false) {}

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
        if ($this->useStatisticsProjections) {
            $scopeSql = ' AND s.sales_point_id = :scopeId';
            $params = ['fromDate' => $input->from->format('Y-m-d'), 'toDate' => $input->toExclusive->format('Y-m-d'), 'scopeId' => $input->salesPointId ?? 0];
            $rows = $this->connection->fetchAllAssociative(
                "SELECT s.product_id, MAX(s.product_name_snapshot) AS name, SUM(s.quantity_sold) AS quantity, COALESCE(SUM(s.gross_sales),0) AS sales_amount
                 FROM daily_product_sales_statistics s
                 WHERE s.business_date >= :fromDate AND s.business_date < :toDate {$scopeSql}
                 GROUP BY s.product_id ORDER BY quantity DESC, s.product_id ASC LIMIT ".$input->limit,
                $params,
            );
            return array_map(fn(array $r) => new TopProduct((int)$r['product_id'], (string)$r['name'], (int)$r['quantity'], (string)$r['sales_amount']), $rows);
        }
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

    public function getUnitsSold(StatisticsQueryInput $input): int
    {
        $filter = $this->salesPointFilter($input, 'o');
        return (int) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(oi.quantity),0)
             FROM order_items oi
             INNER JOIN orders o ON o.id=oi.order_id
             WHERE o.created_at >= :from AND o.created_at < :to
               AND o.status='COMPLETED' {$filter['sql']}",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
    }

    public function getUniqueCustomers(StatisticsQueryInput $input): int
    {
        $filter = $this->salesPointFilter($input, 'o');
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT o.customer_id)
             FROM orders o
             WHERE o.customer_id IS NOT NULL
               AND o.created_at >= :from AND o.created_at < :to
               AND o.status='COMPLETED' {$filter['sql']}",
            $this->params($input, $filter['params']),
            $this->dateRangeTypes($filter['params']),
        );
    }

    public function getCurrentOutstandingDebt(?int $salesPointId = null): string
    {
        $filter = '';
        $params = [];
        if ($salesPointId !== null) {
            $filter = ' AND o.sales_point_id = :salesPointId';
            $params['salesPointId'] = $salesPointId;
        }
        $row = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN d.status='REVERSED' THEN 0 ELSE GREATEST(d.original_amount - COALESCE(dp.paid_amount,0),0) END),0)
             FROM debts d
             INNER JOIN orders o ON o.id=d.order_id
             LEFT JOIN (SELECT debt_id, SUM(amount) paid_amount FROM debt_payments GROUP BY debt_id) dp ON dp.debt_id=d.id
             WHERE 1=1 {$filter}",
            $params,
        );
        return (string) $row;
    }

    public function getTopCustomers(StatisticsQueryInput $input): array
    {
        if ($this->useStatisticsProjections) {
            $scopeSql = ' AND s.sales_point_id = :scopeId';
            $params = ['fromDate' => $input->from->format('Y-m-d'), 'toDate' => $input->toExclusive->format('Y-m-d'), 'scopeId' => $input->salesPointId ?? 0];
            $rows = $this->connection->fetchAllAssociative(
                "SELECT s.customer_id, c.name, SUM(s.order_count) AS order_count, COALESCE(SUM(s.net_spend),0) AS total_spent
                 FROM daily_customer_sales_statistics s INNER JOIN customers c ON c.id=s.customer_id
                 WHERE s.business_date >= :fromDate AND s.business_date < :toDate {$scopeSql}
                 GROUP BY s.customer_id, c.name ORDER BY total_spent DESC, s.customer_id ASC LIMIT ".$input->limit,
                $params,
            );
            return array_map(fn(array $r) => new TopCustomer((int)$r['customer_id'], (string)$r['name'], (int)$r['order_count'], (string)$r['total_spent']), $rows);
        }
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

    /**
     * Return one database-aggregated value for each of the seven requested days.
     * Refund reversals are aggregated separately so the chart follows the same
     * net-sales semantics as getSalesSummary without loading orders into PHP.
     *
     */
    public function getWeeklySales(StatisticsQueryInput $input): WeeklySalesSummary
    {
        $from = $input->from->modify('-6 days');
        $toExclusive = $input->toExclusive;
        $filter = $this->salesPointFilter($input, 'o');
        $params = [
            'from' => $from,
            'to' => $toExclusive,
        ];
        if ($input->salesPointId !== null) {
            $params['salesPointId'] = $input->salesPointId;
        }

        $salesRows = $this->connection->fetchAllAssociative(
            "SELECT DATE(o.created_at) AS day_key,
                    COALESCE(SUM(CASE WHEN o.status IN ('COMPLETED','REFUNDED') THEN o.total ELSE 0 END),0) AS gross_sales
             FROM orders o
             WHERE o.created_at >= :from
               AND o.created_at < :to
               {$filter['sql']}
             GROUP BY DATE(o.created_at)",
            $params,
            $this->dateRangeTypes($filter['params']),
        );

        $refundRows = $this->connection->fetchAllAssociative(
            "SELECT DATE(r.created_at) AS day_key,
                    COALESCE(SUM(r.amount),0) AS refunded_amount
             FROM order_financial_reversals r
             INNER JOIN orders o ON o.id=r.order_id
             WHERE r.type='REFUND'
               AND r.created_at >= :from
               AND r.created_at < :to
               {$filter['sql']}
             GROUP BY DATE(r.created_at)",
            $params,
            $this->dateRangeTypes($filter['params']),
        );

        $salesByDay = [];
        foreach ($salesRows as $row) {
            $salesByDay[(string) $row['day_key']] = (string) $row['gross_sales'];
        }
        $refundsByDay = [];
        foreach ($refundRows as $row) {
            $refundsByDay[(string) $row['day_key']] = (string) $row['refunded_amount'];
        }

        $rawValues = [];
        for ($day = 0; $day < 7; ++$day) {
            $date = $from->modify(sprintf('+%d days', $day));
            $key = $date->format('Y-m-d');
            $rawValues[$key] = $this->decimalSub(
                $salesByDay[$key] ?? '0.00',
                $refundsByDay[$key] ?? '0.00',
            );
        }

        $maxMinor = 0;
        foreach ($rawValues as $value) {
            $maxMinor = max($maxMinor, $this->decimalParts($value));
        }

        $points = [];
        foreach ($rawValues as $key => $value) {
            $date = new \DateTimeImmutable($key, $from->getTimezone());
            $minor = max(0, $this->decimalParts($value));
            $percent = $maxMinor > 0 ? (int) round(($minor / $maxMinor) * 100) : 0;
            $points[] = new WeeklySalesPoint(
                date: $date,
                label: $this->weekdayLabel($date),
                netSales: $value,
                displayLabel: $this->toCompactVndLabel($value),
                barPercent: $percent,
            );
        }

        $totalMinor = 0;
        $highestMinor = 0;
        foreach ($rawValues as $value) {
            $minor = max(0, $this->decimalParts($value));
            $totalMinor += $minor;
            $highestMinor = max($highestMinor, $minor);
        }

        return new WeeklySalesSummary(
            points: $points,
            total: $this->minorToDecimal($totalMinor),
            highest: $this->minorToDecimal($highestMinor),
        );
    }

    public function getStockSnapshot(): StockSnapshot
    {
        $row=$this->connection->fetchAssociative("SELECT COUNT(*) total_products, COALESCE(SUM(is_active=1),0) active_products, COALESCE(SUM(is_active=1 AND stock_quantity <= low_stock_threshold AND stock_quantity > 0),0) low_stock_products, COALESCE(SUM(stock_quantity=0),0) out_of_stock_products, COALESCE(SUM(stock_quantity),0) total_stock_quantity FROM products");
        return new StockSnapshot((int)$row['total_products'],(int)$row['active_products'],(int)$row['low_stock_products'],(int)$row['out_of_stock_products'],(int)$row['total_stock_quantity']);
    }

    private function toCompactVndLabel(string $value): string
    {
        // decimalParts() returns the amount in minor units (1 VND = 100 minor units).
        // Keep the chart label compact and choose the unit from the real VND amount.
        $minor = $this->decimalParts($value);
        $negative = $minor < 0;
        $absoluteMinor = abs($minor);
        $prefix = $negative ? '-' : '';

        $thousand = 100_000;      // 1,000 VND
        $million = 100_000_000;   // 1,000,000 VND
        $billion = 100_000_000_000; // 1,000,000,000 VND

        if ($absoluteMinor >= $billion) {
            return $prefix.$this->formatCompactUnit($absoluteMinor, $billion, 'tỷ');
        }

        if ($absoluteMinor >= $million) {
            return $prefix.$this->formatCompactUnit($absoluteMinor, $million, 'tr');
        }

        if ($absoluteMinor >= $thousand) {
            return $prefix.$this->formatCompactUnit($absoluteMinor, $thousand, 'k');
        }

        return $prefix.number_format(intdiv($absoluteMinor, 100), 0, ',', '.').' ₫';
    }

    private function formatCompactUnit(int $minor, int $unitMinor, string $unit): string
    {
        $scaled = $minor / $unitMinor;
        $rounded = round($scaled, 1);
        $formatted = fmod($rounded, 1.0) === 0.0
            ? number_format($rounded, 0, ',', '.')
            : number_format($rounded, 1, ',', '.');

        return $formatted.' '.$unit;
    }

    private function minorToDecimal(int $minor): string
    {
        return sprintf('%d.%02d', intdiv(max(0, $minor), 100), max(0, $minor) % 100);
    }

    private function weekdayLabel(\DateTimeImmutable $date): string
    {
        return match ((int) $date->format('N')) {
            1 => 'T2',
            2 => 'T3',
            3 => 'T4',
            4 => 'T5',
            5 => 'T6',
            6 => 'T7',
            default => 'CN',
        };
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
