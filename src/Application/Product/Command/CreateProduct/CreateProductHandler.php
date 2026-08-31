<?php

declare(strict_types=1);

namespace App\Application\Product\Command\CreateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final readonly class CreateProductHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(CreateProductInput $input): int
    {
        $this->validateInput($input);

        return $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($input): int {
                if (
                    $input->sku !== null
                    && $this->productRepository->existsBySku($input->sku)
                ) {
                    throw new \DomainException(
                        'A product with this SKU already exists.',
                    );
                }

                $product = new Product(
                    name: $input->name,
                    sellingPrice: $input->sellingPrice,
                    sku: $input->sku,
                    unit: $input->unit,
                    costPrice: $input->costPrice,
                    lowStockThreshold: $input->lowStockThreshold,
                    note: $input->note,
                );

                $this->productRepository->save($product);

                $transaction->flush();

                $id = $product->getId();

                if ($id === null) {
                    throw new \LogicException(
                        'Product ID was not generated after flush.',
                    );
                }

                return $id;
            },
        );
    }

    private function validateInput(CreateProductInput $input): void
    {
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
