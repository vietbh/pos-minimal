<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UpdateProductAttributes;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\ProductAttribute;
use App\Domain\Product\Repository\ProductAttributeRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final readonly class UpdateProductAttributesHandler
{
    public function __construct(
        private TransactionManagerInterface $transactions,
        private ProductRepositoryInterface $products,
        private ProductAttributeRepositoryInterface $attributes,
    ) {}

    /** @param list<array{name:string,value:string,selectable?:bool}> $items */
    public function __invoke(int $productId, array $items): void
    {
        $this->transactions->run(function (TransactionContextInterface $tx) use ($productId, $items): void {
            $product = $this->products->findById($productId);
            if ($product === null) throw new \DomainException('Product not found.');

            $normalized = [];
            foreach ($items as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                $value = trim((string) ($item['value'] ?? ''));
                if ($name === '' && $value === '') continue;
                if ($name === '' || $value === '') throw new \InvalidArgumentException('Thuộc tính phải có cả tên và giá trị.');
                $key = mb_strtolower($name . "\0" . $value);
                if (isset($normalized[$key])) continue;
                if (count($normalized) >= 50) throw new \InvalidArgumentException('Một sản phẩm chỉ được tối đa 50 thuộc tính bổ sung.');
                $normalized[$key] = ['name' => $name, 'value' => $value, 'selectable' => (bool) ($item['selectable'] ?? true)];
            }

            $this->attributes->deleteByProduct($product);
            foreach (array_values($normalized) as $index => $item) {
                $this->attributes->save(new ProductAttribute($product, $item['name'], $item['value'], $index, $item['selectable']));
            }
            $tx->flush();
        });
    }
}
