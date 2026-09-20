<?php

declare(strict_types=1);
namespace App\Application\Product\Query;
final readonly class ProductCatalogInput
{
    public function __construct(public string $query = '', public ?int $categoryId = null, public string $sort = 'name_asc', public int $page = 1, public int $limit = 20) {}
}
