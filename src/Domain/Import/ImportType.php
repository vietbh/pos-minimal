<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum ImportType: string
{
    case PRODUCT = 'product';
    case STOCK = 'stock';
    case CUSTOMER = 'customer';
}
