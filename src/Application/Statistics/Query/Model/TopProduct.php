<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class TopProduct
{
    public function __construct(public int $productId, public string $name, public int $quantity, public string $salesAmount) {}
}
