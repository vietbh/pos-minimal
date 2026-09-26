<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Customer\Query\CustomerQueryRepositoryInterface;
use App\Application\Customer\Query\GetCustomer\{CustomerDetailResult, CustomerOrderSummaryResult};
use App\Application\Debt\Query\ListDebts\DebtListItemResult;
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtPayment;
use App\Domain\Debt\Enum\DebtStatus;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Shared\ValueObject\Money;
use App\Application\Customer\Query\SearchCustomers\CustomerSearchResult;
use App\Domain\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerQueryRepository implements CustomerQueryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findCustomerDetail(int $id): ?CustomerDetailResult
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Customer ID must be greater than zero.');
        }

        /** @var Customer|null $customer */
        $customer = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$customer instanceof Customer || $customer->getId() === null) {
            return null;
        }

        $debtRows = $this->entityManager->createQueryBuilder()
            ->select(
                'd.id AS id',
                'IDENTITY(d.customer) AS customerId',
                'c.name AS customerName',
                'IDENTITY(d.order) AS orderId',
                'o.orderNumber AS orderNumber',
                'd.originalAmount AS originalAmount',
                'd.status AS status',
                'd.createdAt AS createdAt',
                'COALESCE(SUM(dp.amount), 0) AS paidAmount',
            )
            ->from(Debt::class, 'd')
            ->join('d.customer', 'c')
            ->join('d.order', 'o')
            ->leftJoin(DebtPayment::class, 'dp', 'WITH', 'dp.debt = d')
            ->where('c.id = :customerId')
            ->setParameter('customerId', $id)
            ->groupBy('d.id, c.id, c.name, o.id, o.orderNumber, d.originalAmount, d.status, d.createdAt')
            ->orderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $debts = [];
        foreach ($debtRows as $row) {
            $original = Money::fromDecimal((string) $row['originalAmount']);
            $paid = Money::fromDecimal((string) $row['paidAmount']);
            $remaining = $paid->isGreaterThanOrEqual($original) ? Money::zero() : $original->subtract($paid);

            $debts[] = new DebtListItemResult(
                (int) $row['id'],
                (int) $row['customerId'],
                (string) $row['customerName'],
                (int) $row['orderId'],
                (string) $row['orderNumber'],
                $original->toDecimal(),
                $paid->toDecimal(),
                $remaining->toDecimal(),
                $row['status'] instanceof \BackedEnum ? $row['status']->value : (string) $row['status'],
                $this->toDateTimeImmutable($row['createdAt']),
            );
        }

        $debtOriginalValue = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(d.originalAmount), 0)')
            ->from(Debt::class, 'd')
            ->where('d.customer = :customerId')
            ->andWhere('d.status IN (:activeStatuses)')
            ->setParameter('customerId', $id)
            ->setParameter('activeStatuses', [DebtStatus::OPEN, DebtStatus::PARTIALLY_PAID])
            ->getQuery()
            ->getSingleScalarResult();

        $debtPaidValue = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(dp.amount), 0)')
            ->from(DebtPayment::class, 'dp')
            ->join('dp.debt', 'd')
            ->where('d.customer = :customerId')
            ->andWhere('d.status IN (:activeStatuses)')
            ->setParameter('customerId', $id)
            ->setParameter('activeStatuses', [DebtStatus::OPEN, DebtStatus::PARTIALLY_PAID])
            ->getQuery()
            ->getSingleScalarResult();

        $debtOriginal = Money::fromDecimal((string) $debtOriginalValue);
        $debtPaid = Money::fromDecimal((string) $debtPaidValue);
        $debtRemaining = $debtPaid->isGreaterThanOrEqual($debtOriginal)
            ? Money::zero()
            : $debtOriginal->subtract($debtPaid);

        $debtCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Debt::class, 'd')
            ->where('d.customer = :customerId')
            ->setParameter('customerId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        $orderCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->where('o.customer = :customerId')
            ->setParameter('customerId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        $orderRows = $this->entityManager->createQueryBuilder()
            ->select('o.id AS id', 'o.orderNumber AS orderNumber', 'o.status AS status', 'o.total AS total', 'o.createdAt AS createdAt')
            ->from(Order::class, 'o')
            ->where('o.customer = :customerId')
            ->setParameter('customerId', $id)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $orders = [];
        foreach ($orderRows as $row) {
            $orders[] = new CustomerOrderSummaryResult(
                (int) $row['id'],
                (string) $row['orderNumber'],
                $row['status'] instanceof OrderStatus ? $row['status'] : OrderStatus::from((string) $row['status']),
                Money::fromDecimal((string) $row['total'])->toDecimal(),
                $this->toDateTimeImmutable($row['createdAt']),
            );
        }

        return new CustomerDetailResult(
            $customer->getId(),
            $customer->getName(),
            $customer->getPhone(),
            $customer->getNote(),
            $customer->getDefaultDiscountPercent(),
            $customer->getCreatedAt(),
            $customer->getUpdatedAt(),
            $debtCount,
            $debtOriginal->toDecimal(),
            $debtPaid->toDecimal(),
            $debtRemaining->toDecimal(),
            $debts,
            $orders,
            $orderCount,
        );
    }

    /**
     * @return list<CustomerSearchResult>
     */
    /**
     * @return list<CustomerSearchResult>
     */
    public function listCustomers(int $limit): array
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('Customer list limit must be greater than zero.');
        }

        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('c.id AS id', 'c.name AS name', 'c.phone AS phone', 'c.defaultDiscountPercent AS defaultDiscountPercent')
            ->from(Customer::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): CustomerSearchResult => new CustomerSearchResult(
                id: (int) $row['id'],
                name: (string) $row['name'],
                phone: $row['phone'] !== null ? (string) $row['phone'] : null,
                defaultDiscountPercent: (int) ($row['defaultDiscountPercent'] ?? 0),
            ),
            $rows,
        );
    }

    private function toDateTimeImmutable(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        return new \DateTimeImmutable((string) $value);
    }

    public function searchCustomers(
        string $query,
        int $limit,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $pattern = '%'.$query.'%';

        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'c.id AS id',
                'c.name AS name',
                'c.phone AS phone',
                'c.defaultDiscountPercent AS defaultDiscountPercent',
            )
            ->from(Customer::class, 'c')
            ->where(
                '(c.name LIKE :query OR c.phone LIKE :query)',
            )
            ->setParameter('query', $pattern)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $results = [];

        foreach ($rows as $row) {
            $results[] = new CustomerSearchResult(
                id: (int) $row['id'],
                name: (string) $row['name'],
                phone: $row['phone'] !== null
                    ? (string) $row['phone']
                    : null,
                defaultDiscountPercent: (int) ($row['defaultDiscountPercent'] ?? 0),
            );
        }

        return $results;
    }
}
