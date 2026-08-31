<?php

declare(strict_types=1);

namespace App\Application\Product\Command\ActivateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final readonly class ActivateProductHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(ActivateProductInput $input): void
    {
        $this->change(
            $input->productId,
            true,
        );
    }

    private function change(
        int $productId,
        bool $activate,
    ): void {
        if ($productId <= 0) {
            throw new \InvalidArgumentException(
                'Product ID must be greater than zero.',
            );
        }

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use (
                $productId,
                $activate,
            ): void {
                $product = $this->productRepository->findById(
                    $productId,
                );

                if ($product === null) {
                    throw new \DomainException(
                        sprintf(
                            'Product %d was not found.',
                            $productId,
                        ),
                    );
                }

                if ($activate) {
                    $product->activate();
                } else {
                    $product->deactivate();
                }

                $this->productRepository->save($product);

                $transaction->flush();
            },
        );
    }
}
