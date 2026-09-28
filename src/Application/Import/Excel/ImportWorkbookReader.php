<?php

declare(strict_types=1);

namespace App\Application\Import\Excel;

use App\Domain\Import\ImportType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final readonly class ImportWorkbookReader
{
    public const PRODUCT_SHEET = 'Products';
    public const STOCK_SHEET = 'Stock Adjustments';
    public const CUSTOMER_SHEET = 'Customers';
    public const PRODUCT_VERSION = 'product-v2';
    public const STOCK_VERSION = 'stock-v3';
    public const CUSTOMER_VERSION = 'customer-v1';

    /** @return iterable<int, array<string, string>> */
    public function rows(string $path, ImportType $type): iterable
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        try {
            $sheetName = match ($type) { ImportType::PRODUCT => self::PRODUCT_SHEET, ImportType::STOCK => self::STOCK_SHEET, ImportType::CUSTOMER => self::CUSTOMER_SHEET }; 
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                throw new \InvalidArgumentException('IMPORT_INVALID_TEMPLATE: Required worksheet is missing.');
            }
            $expected = match ($type) {
                ImportType::PRODUCT => ['SKU', 'Name', 'Category', 'Unit', 'Selling Price', 'Cost Price', 'Low Stock Threshold', 'Note'],
                ImportType::STOCK => ['Product Name', 'Quantity Change', 'Reason'],
                ImportType::CUSTOMER => ['Name', 'Phone', 'Default Discount Percent', 'Note'],
            };
            $stockDisplayHeaders = ['Product Name', 'Category (display only)', 'Current Stock (display only)', 'Quantity Change', 'Reason'];

            // Do not use getHighestColumn() here. Excel workbooks commonly contain
            // formatting/default column dimensions far beyond the actual data range.
            // In that case PhpSpreadsheet reports e.g. Z as the highest column even
            // though the import contract only contains A:H. Reading the entire
            // reported range makes valid files fail header validation.
            $header = array_map(
                static fn ($value): string => trim((string) $value),
                $sheet->rangeToArray('A1:E1', '', true, true, false)[0] ?? []
            );

            if ($type === ImportType::STOCK && $header === $stockDisplayHeaders) {
                $max = $sheet->getHighestRow();
                for ($rowNumber = 2; $rowNumber <= $max; ++$rowNumber) {
                    $values = array_map(
                        static fn ($value): string => trim((string) $value),
                        $sheet->rangeToArray('A'.$rowNumber.':E'.$rowNumber, '', true, true, false)[0] ?? []
                    );
                    // Rows from the downloaded catalog with no quantity change are informational only.
                    if (trim($values[0] ?? '') === '' || trim($values[3] ?? '') === '') {
                        continue;
                    }
                    yield $rowNumber => [
                        'Product Name' => $values[0] ?? '',
                        'Quantity Change' => $values[3] ?? '',
                        'Reason' => $values[4] ?? '',
                    ];
                }
                return;
            }

            $lastExpectedColumn = Coordinate::stringFromColumnIndex(count($expected));
            $header = array_map(
                static fn ($value): string => trim((string) $value),
                $sheet->rangeToArray('A1:'.$lastExpectedColumn.'1', '', true, true, false)[0] ?? []
            );
            if ($header !== $expected) {
                throw new \InvalidArgumentException('IMPORT_INVALID_HEADER: Workbook headers do not match the selected template.');
            }
            $max = $sheet->getHighestRow();
            for ($rowNumber = 2; $rowNumber <= $max; ++$rowNumber) {
                $values = array_map(
                    static fn ($value): string => trim((string) $value),
                    $sheet->rangeToArray('A'.$rowNumber.':'.$lastExpectedColumn.$rowNumber, '', true, true, false)[0] ?? []
                );
                if ($this->isBlankRow($values)) {
                    continue;
                }
                yield $rowNumber => array_combine($expected, array_pad($values, count($expected), '')) ?: [];
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @return list<string> */
    public function headers(ImportType $type): array
    {
        return match ($type) {
            ImportType::PRODUCT => ['SKU', 'Name', 'Category', 'Unit', 'Selling Price', 'Cost Price', 'Low Stock Threshold', 'Note'],
            ImportType::STOCK => ['Product Name', 'Quantity Change', 'Reason'],
            ImportType::CUSTOMER => ['Name', 'Phone', 'Default Discount Percent', 'Note'],
        };
    }

    public function version(ImportType $type): string
    {
        return match ($type) { ImportType::PRODUCT => self::PRODUCT_VERSION, ImportType::STOCK => self::STOCK_VERSION, ImportType::CUSTOMER => self::CUSTOMER_VERSION }; 
    }

    /** @param list<string> $values */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') return false;
        }
        return true;
    }
}
