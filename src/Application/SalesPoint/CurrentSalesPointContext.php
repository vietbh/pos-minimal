<?php

declare(strict_types=1);

namespace App\Application\SalesPoint;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CurrentSalesPointContext
{
    private const SESSION_KEY = '_mobile_pos_sales_point_id';

    public function __construct(private RequestStack $requestStack) {}

    public function getId(): ?int
    {
        $id = $this->requestStack->getSession()->get(self::SESSION_KEY);
        return is_int($id) && $id > 0 ? $id : (is_numeric($id) && (int) $id > 0 ? (int) $id : null);
    }

    public function setId(int $id): void { $this->requestStack->getSession()->set(self::SESSION_KEY, $id); }
    public function clear(): void { $this->requestStack->getSession()->remove(self::SESSION_KEY); }
}
