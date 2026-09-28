<?php

declare(strict_types=1);

namespace App\Application\Product\Command\CreateProduct;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;

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
                    $category = $this->categoryRepository->findByNormalizedName($categoryName);

                    if ($category !== null) {
                        if (!$category->isActive()) {
                            throw new \DomainException('Product category was found but is inactive.');
                        }
                    } else {
                        $category = new \App\Domain\Product\ProductCategory($categoryName);
                        $this->categoryRepository->save($category);
                    }
                }

                $sku = $input->sku ?? $this->generateSku($input->name, $category?->getName());

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

    private function generateSku(string $name, ?string $categoryName = null): Sku
    {
        $categoryPart = $this->slugPart($categoryName ?? '');
        $namePart = $this->slugPart($name);

        if ($categoryPart !== '' && ($namePart === $categoryPart || str_starts_with($namePart, $categoryPart.'-'))) {
            $namePart = trim(substr($namePart, strlen($categoryPart)), '-');
        }

        $base = implode('-', array_values(array_filter([
            $categoryPart,
            $namePart,
        ])));
        $base = substr($base !== '' ? $base : 'PRODUCT', 0, 90);

        $candidate = new Sku($base);
        if (!$this->productRepository->existsBySku($candidate)) {
            return $candidate;
        }

        // Keep the generated SKU human-readable and stable after creation.
        // If the readable base is already taken, append a short random suffix
        // rather than relying on MAX()+1, which is unsafe under concurrent creates.
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $suffix = strtoupper(bin2hex(random_bytes(3)));
            $candidateValue = substr($base, 0, 100 - 7).'_'.$suffix;
            $candidate = new Sku($candidateValue);

            if (!$this->productRepository->existsBySku($candidate)) {
                return $candidate;
            }
        }

        throw new \DomainException('Unable to generate a unique SKU for this product.');
    }

    private function slugPart(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $ascii = is_string($ascii) && $ascii !== '' ? $ascii : trim($value);
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', '-', $ascii) ?? '';

        return trim($ascii, '-');
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
