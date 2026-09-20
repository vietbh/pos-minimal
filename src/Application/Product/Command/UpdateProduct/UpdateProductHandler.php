<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UpdateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;

final readonly class UpdateProductHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
        private ProductCategoryRepositoryInterface $categoryRepository,
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

                $sku = $input->sku ?? $this->generateSku($input->name, $input->productId);

                if (
                    $this->productRepository->existsBySku(
                        $sku,
                        $input->productId,
                    )
                ) {
                    throw new \DomainException(
                        'A product with this SKU already exists.',
                    );
                }

                $category = null;
                if ($input->categoryId !== null) {
                    $category = $this->categoryRepository->findById($input->categoryId);
                    if ($category === null || !$category->isActive()) throw new \DomainException('Product category was not found or is inactive.');
                }

                $product->rename($input->name);
                $product->changeCategory($category);
                $product->changeSku($sku);
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

    private function generateSku(string $name, int $excludeProductId): Sku
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name));
        $ascii = is_string($ascii) && $ascii !== '' ? $ascii : trim($name);
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', '-', $ascii) ?? '';
        $base = trim($ascii, '-');
        $base = substr($base !== '' ? $base : 'PRODUCT', 0, 92);

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $candidate = $base . '-' . strtoupper(bin2hex(random_bytes(3)));
            $sku = new Sku(substr($candidate, 0, 100));
            if (!$this->productRepository->existsBySku($sku, $excludeProductId)) {
                return $sku;
            }
        }

        throw new \DomainException('Unable to generate a unique SKU for this product.');
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
