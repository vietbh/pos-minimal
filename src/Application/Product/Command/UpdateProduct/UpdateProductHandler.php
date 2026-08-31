<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UpdateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final readonly class UpdateProductHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(UpdateProductInput $input): void
    {
        $this->validateInput($input);

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($input): void {
                $product = $this->productRepository->findById(
                    $input->productId,
                );

                if ($product === null) {
                    throw new \DomainException(
                        sprintf(
                            'Product %d was not found.',
                            $input->productId,
                        ),
                    );
                }

                if (
                    $input->sku !== null
                    && $this->productRepository->existsBySku(
                        $input->sku,
                        $input->productId,
                    )
                ) {
                    throw new \DomainException(
                        'A product with this SKU already exists.',
                    );
                }

                $product->rename($input->name);
                $product->changeSku($input->sku);
                $product->changeUnit($input->unit);
                $product->changeCostPrice($input->costPrice);
                $product->changeLowStockThreshold(
                    $input->lowStockThreshold,
                );
                $product->changeNote($input->note);

                $this->productRepository->save($product);

                $transaction->flush();
            },
        );
    }

    private function validateInput(UpdateProductInput $input): void
    {
        if ($input->productId <= 0) {
            throw new \InvalidArgumentException(
                'Product ID must be greater than zero.',
            );
        }

        if (trim($input->name) === '') {
            throw new \InvalidArgumentException(
                'Product name cannot be empty.',
            );
        }

        if ($input->lowStockThreshold < 0) {
            throw new \InvalidArgumentException(
                'Low stock threshold cannot be negative.',
            );
        }
    }
}
