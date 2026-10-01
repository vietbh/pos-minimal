<?php

declare(strict_types=1);

namespace App\Domain\Kpi;

use App\Domain\User\User;
use App\Domain\SalesPoint\SalesPoint;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'kpi_goals')]
#[ORM\Index(name: 'idx_kpi_goal_period', columns: ['start_date', 'end_date'])]
#[ORM\Index(name: 'idx_kpi_goal_metric', columns: ['metric'])]
#[ORM\Index(name: 'idx_kpi_goal_created_by', columns: ['created_by'])]
#[ORM\Index(name: 'idx_kpi_goal_sales_point', columns: ['sales_point_id'])]
class KpiGoal
{
    public const METRIC_REVENUE = 'revenue';
    public const METRIC_UNITS_SOLD = 'units_sold';
    public const METRIC_CUSTOMERS = 'customers';
    public const METRIC_OUTSTANDING_DEBT = 'outstanding_debt';

    public const DIRECTION_AT_LEAST = 'at_least';
    public const DIRECTION_AT_MOST = 'at_most';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $metric;

    #[ORM\Column(length: 20)]
    private string $direction;

    #[ORM\Column(name: 'target_value', type: 'decimal', precision: 20, scale: 2)]
    private string $targetValue;

    #[ORM\Column(name: 'initial_value', type: 'decimal', precision: 20, scale: 2)]
    private string $initialValue;

    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\ManyToOne(targetEntity: SalesPoint::class)]
    #[ORM\JoinColumn(name: 'sales_point_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?SalesPoint $salesPoint;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $metric,
        string $direction,
        string $targetValue,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        User $createdBy,
        string $initialValue = '0.00',
        ?SalesPoint $salesPoint = null,
    ) {
        if (!in_array($metric, [self::METRIC_REVENUE, self::METRIC_UNITS_SOLD, self::METRIC_CUSTOMERS, self::METRIC_OUTSTANDING_DEBT], true)) {
            throw new \InvalidArgumentException('Unsupported KPI metric.');
        }
        if (!in_array($direction, [self::DIRECTION_AT_LEAST, self::DIRECTION_AT_MOST], true)) {
            throw new \InvalidArgumentException('Unsupported KPI direction.');
        }
        if ((float) $targetValue <= 0) {
            throw new \InvalidArgumentException('KPI target must be greater than zero.');
        }
        if ($endDate < $startDate) {
            throw new \InvalidArgumentException('KPI end date must not be before start date.');
        }
        if ($metric === self::METRIC_OUTSTANDING_DEBT && $direction !== self::DIRECTION_AT_MOST) {
            throw new \InvalidArgumentException('Outstanding debt KPI must use an at-most target.');
        }

        $this->metric = $metric;
        $this->direction = $direction;
        $this->targetValue = self::normalizeDecimal($targetValue);
        $this->initialValue = self::normalizeDecimal($initialValue);
        $this->startDate = $startDate->setTime(0, 0);
        $this->endDate = $endDate->setTime(0, 0);
        $this->salesPoint = $salesPoint;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMetric(): string { return $this->metric; }
    public function getDirection(): string { return $this->direction; }
    public function getTargetValue(): string { return $this->targetValue; }
    public function getInitialValue(): string { return $this->initialValue; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getSalesPoint(): ?SalesPoint { return $this->salesPoint; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    private static function normalizeDecimal(string $value): string
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('KPI value must be numeric.');
        }
        return number_format((float) $value, 2, '.', '');
    }
}
