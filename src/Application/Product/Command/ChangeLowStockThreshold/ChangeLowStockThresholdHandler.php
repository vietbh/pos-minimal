<?php

declare(strict_types=1);

namespace App\Application\Product\Command\ChangeLowStockThreshold;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final readonly class ChangeLowStockThresholdHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(ChangeLowStockThresholdInput $input): void
    {
        if ($input->productId <= 0) {
            throw new \InvalidArgumentException('Product ID must be greater than zero.');
        }

        if ($input->lowStockThreshold < 0) {
            throw new \InvalidArgumentException('Low stock threshold cannot be negative.');
        }

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($input): void {
                $product = $this->productRepository->findById($input->productId);

                if ($product === null) {
                    throw new \DomainException(sprintf('Product %d was not found.', $input->productId));
                }

                $product->changeLowStockThreshold($input->lowStockThreshold);
                $this->productRepository->save($product);
                $transaction->flush();
            },
        );
    }
}
