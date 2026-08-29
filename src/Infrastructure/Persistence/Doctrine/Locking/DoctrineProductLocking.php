<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Locking;

use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Order\Command\Checkout\ProductNotFound;
use App\Domain\Product\Product;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductLocking implements ProductLockingInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function lock(int $productId): Product
    {
        $product = $this->entityManager->find(
            Product::class,
            $productId,
            LockMode::PESSIMISTIC_WRITE,
        );

        if (!$product instanceof Product) {
            throw new ProductNotFound($productId);
        }

        return $product;
    }
}
