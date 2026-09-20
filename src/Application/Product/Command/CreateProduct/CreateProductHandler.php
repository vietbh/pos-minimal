<?php

declare(strict_types=1);

namespace App\Application\Product\Command\CreateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;

final readonly class CreateProductHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private ProductRepositoryInterface $productRepository,
        private ProductCategoryRepositoryInterface $categoryRepository,
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

                $category = null;
                $categoryName = trim((string) ($input->categoryName ?? ''));

                if ($input->categoryId !== null && $categoryName !== '') {
                    throw new \DomainException('Chỉ chọn một danh mục hoặc tạo danh mục mới.');
                }

                if ($input->categoryId !== null) {
                    $category = $this->categoryRepository->findById($input->categoryId);
                    if ($category === null || !$category->isActive()) {
                        throw new \DomainException('Product category was not found or is inactive.');
                    }
                } elseif ($categoryName !== '') {
                    if ($this->categoryRepository->existsByName($categoryName)) {
                        throw new \DomainException('A category with this name already exists.');
                    }

                    $category = new \App\Domain\Product\ProductCategory($categoryName);
                    $this->categoryRepository->save($category);
                }

                $sku = $input->sku ?? $this->generateSku($input->name);

                $product = new Product(
                    name: $input->name,
                    sellingPrice: $input->sellingPrice,
                    sku: $sku,
                    unit: $input->unit,
                    costPrice: $input->costPrice,
                    lowStockThreshold: $input->lowStockThreshold,
                    note: $input->note,
                );

                $product->changeCategory($category);
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

    private function generateSku(string $name): Sku
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name));
        $ascii = is_string($ascii) && $ascii !== '' ? $ascii : trim($name);
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', '-', $ascii) ?? '';
        $base = trim($ascii, '-');
        $base = substr($base !== '' ? $base : 'PRODUCT', 0, 100);

        // Keep the SKU human-readable while making automatic generation resistant
        // to collisions even when many products share the same name.
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $random = strtoupper(bin2hex(random_bytes(3)));
            $suffix = '-' . $random;
            $candidate = substr($base, 0, 100 - strlen($suffix)) . $suffix;
            $sku = new Sku($candidate);

            if (!$this->productRepository->existsBySku($sku)) {
                return $sku;
            }
        }

        throw new \DomainException('Unable to generate a unique SKU for this product.');
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
