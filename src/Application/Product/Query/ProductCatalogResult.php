<?php

declare(strict_types=1);
namespace App\Application\Product\Query;
final readonly class ProductCatalogResult
{
    public function __construct(public array $items, public int $page, public int $limit, public int $total, public int $totalPages) {}
}
