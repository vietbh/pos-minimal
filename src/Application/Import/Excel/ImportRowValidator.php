<?php

declare(strict_types=1);

namespace App\Application\Import\Excel;

use App\Domain\Import\ImportType;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;

final readonly class ImportRowValidator
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductCategoryRepositoryInterface $categories,
        private CustomerRepositoryInterface $customers,
    ) {}

    /** @return list<array{field:string,code:string,message:string,value:?string}> */
    public function validate(ImportType $type, array $row): array
    {
        return match ($type) { ImportType::PRODUCT => $this->validateProduct($row), ImportType::STOCK => $this->validateStock($row), ImportType::CUSTOMER => $this->validateCustomer($row) };
    }

    /** @param array<string,string> $row */
    private function validateProduct(array $row): array
    {
        $errors = [];
        $sku = trim($row['SKU'] ?? '');
        $name = trim($row['Name'] ?? '');
        $categoryName = trim($row['Category'] ?? '');
        $unit = trim($row['Unit'] ?? '');
        $selling = trim($row['Selling Price'] ?? '');
        $cost = trim($row['Cost Price'] ?? '');
        $threshold = trim($row['Low Stock Threshold'] ?? '');
        $note = trim($row['Note'] ?? '');

        if ($sku !== '') {
            if (mb_strlen($sku) > 100) {
                $errors[] = $this->error('SKU', 'IMPORT_INVALID_LENGTH', 'SKU cannot exceed 100 characters.', $sku);
            } else {
                try { $skuVo = new Sku($sku); } catch (\Throwable) { $skuVo = null; }
                if ($skuVo !== null && $this->products->existsBySku($skuVo)) {
                    $errors[] = $this->error('SKU', 'IMPORT_PRODUCT_ALREADY_EXISTS', 'A product with this SKU already exists.', $sku);
                }
            }
        }
        if ($name === '') $errors[] = $this->error('Name', 'IMPORT_REQUIRED_FIELD', 'Product name is required.', $name);
        elseif (mb_strlen($name) > 255) $errors[] = $this->error('Name', 'IMPORT_INVALID_LENGTH', 'Product name cannot exceed 255 characters.', $name);
        if (mb_strlen($categoryName) > 150) {
            $errors[] = $this->error('Category', 'IMPORT_INVALID_LENGTH', 'Category cannot exceed 150 characters.', $categoryName);
        } elseif ($categoryName !== '') {
            $category = $this->categories->findByNormalizedName($categoryName);
            if ($category !== null && !$category->isActive()) {
                $errors[] = $this->error('Category', 'IMPORT_CATEGORY_NOT_FOUND', 'Product category was found but is inactive.', $categoryName);
            }
        }
        if ($unit !== '' && mb_strlen($unit) > 50) $errors[] = $this->error('Unit', 'IMPORT_INVALID_LENGTH', 'Unit cannot exceed 50 characters.', $unit);
        $errors = array_merge($errors, $this->validateMoney('Selling Price', $selling, true));
        if ($cost !== '') $errors = array_merge($errors, $this->validateMoney('Cost Price', $cost, false));
        if ($threshold === '' || !ctype_digit($threshold)) $errors[] = $this->error('Low Stock Threshold', 'IMPORT_INVALID_INTEGER', 'Low Stock Threshold must be a non-negative integer.', $threshold);
        if (mb_strlen($note) > 2000) $errors[] = $this->error('Note', 'IMPORT_INVALID_LENGTH', 'Note is too long.', $note);
        return $errors;
    }

    /** @param array<string,string> $row */
    private function validateStock(array $row): array
    {
        $errors = [];
        $productName = trim($row['Product Name'] ?? '');
        $quantity = trim($row['Quantity Change'] ?? '');
        $reason = trim($row['Reason'] ?? '');
        if ($productName === '') $errors[] = $this->error('Product Name', 'IMPORT_REQUIRED_FIELD', 'Product name is required.', $productName);
        elseif (mb_strlen($productName) > 255) $errors[] = $this->error('Product Name', 'IMPORT_INVALID_LENGTH', 'Product name cannot exceed 255 characters.', $productName);
        else {
            $product = $this->products->findByName($productName);
            if ($product === null) $errors[] = $this->error('Product Name', 'IMPORT_PRODUCT_NOT_FOUND', 'Product was not found.', $productName);
        }
        if ($quantity === '' || !preg_match('/^-?\d+$/', $quantity) || (int) $quantity === 0) $errors[] = $this->error('Quantity Change', 'IMPORT_INVALID_INTEGER', 'Quantity Change must be a non-zero integer.', $quantity);
        if (mb_strlen($reason) > 255) $errors[] = $this->error('Reason', 'IMPORT_INVALID_LENGTH', 'Reason cannot exceed 255 characters.', $reason);
        return $errors;
    }


    /** @param array<string,string> $row */
    private function validateCustomer(array $row): array
    {
        $errors = [];
        $name = trim($row['Name'] ?? '');
        $phone = trim($row['Phone'] ?? '');
        $discount = trim($row['Default Discount Percent'] ?? '');
        $note = trim($row['Note'] ?? '');
        if ($name === '') $errors[] = $this->error('Name', 'IMPORT_REQUIRED_FIELD', 'Customer name is required.', $name);
        elseif (mb_strlen($name) > 255) $errors[] = $this->error('Name', 'IMPORT_INVALID_LENGTH', 'Customer name cannot exceed 255 characters.', $name);
        if ($phone !== '') {
            if (mb_strlen($phone) > 30) $errors[] = $this->error('Phone', 'IMPORT_INVALID_LENGTH', 'Customer phone cannot exceed 30 characters.', $phone);
            elseif ($this->customers->existsByPhone($phone)) $errors[] = $this->error('Phone', 'IMPORT_CUSTOMER_ALREADY_EXISTS', 'A customer with this phone already exists.', $phone);
        }
        if ($discount === '' || !ctype_digit($discount) || (int) $discount < 0 || (int) $discount > 100) $errors[] = $this->error('Default Discount Percent', 'IMPORT_INVALID_INTEGER', 'Default Discount Percent must be an integer between 0 and 100.', $discount);
        if (mb_strlen($note) > 2000) $errors[] = $this->error('Note', 'IMPORT_INVALID_LENGTH', 'Note is too long.', $note);
        return $errors;
    }

    /** @return list<array{field:string,code:string,message:string,value:?string}> */
    private function validateMoney(string $field, string $value, bool $required): array
    {
        if ($value === '') return $required ? [$this->error($field, 'IMPORT_REQUIRED_FIELD', $field.' is required.', $value)] : [];
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) return [$this->error($field, 'IMPORT_INVALID_MONEY', $field.' must be a non-negative decimal with up to 2 decimal places.', $value)];
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if ($fraction !== '' && preg_match('/[^0]/', $fraction)) return [$this->error($field, 'IMPORT_INVALID_MONEY', 'Vietnamese Dong does not support fractional amounts.', $value)];
        return [];
    }

    /** @return array{field:string,code:string,message:string,value:?string} */
    private function error(string $field, string $code, string $message, ?string $value): array
    {
        return compact('field', 'code', 'message', 'value');
    }
}
