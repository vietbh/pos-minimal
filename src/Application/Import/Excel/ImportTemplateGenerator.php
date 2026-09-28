<?php

declare(strict_types=1);

namespace App\Application\Import\Excel;

use App\Domain\Import\ImportType;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final readonly class ImportTemplateGenerator
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CustomerRepositoryInterface $customers,
    ) {
    }

    public function generate(ImportType $type): string
    {
        $spreadsheet = new Spreadsheet();
        $sheetName = match ($type) {
            ImportType::PRODUCT => ImportWorkbookReader::PRODUCT_SHEET,
            ImportType::STOCK => ImportWorkbookReader::STOCK_SHEET,
            ImportType::CUSTOMER => ImportWorkbookReader::CUSTOMER_SHEET,
        };
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        $headers = match ($type) {
            ImportType::PRODUCT => ['SKU', 'Name', 'Category', 'Unit', 'Selling Price', 'Cost Price', 'Low Stock Threshold', 'Note'],
            ImportType::STOCK => ['Product Name', 'Category (display only)', 'Current Stock (display only)', 'Quantity Change', 'Reason'],
            ImportType::CUSTOMER => ['Name', 'Phone', 'Default Discount Percent', 'Note'],
        };
        $sample = match ($type) {
            ImportType::PRODUCT => ['', 'Bia 333', 'Bia', 'lon', '12000', '9000', '5', 'DELETE THIS SAMPLE ROW BEFORE IMPORT'],
            ImportType::STOCK => ['', '', '', '', ''],
            ImportType::CUSTOMER => ['Nguyễn Văn A', '0900000000', '0', 'DELETE THIS SAMPLE ROW BEFORE IMPORT'],
        };
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.chr(64 + count($headers)).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        if ($type === ImportType::STOCK) {
            $products = $this->products->findAllActiveOrderedByName();
            $row = 2;
            foreach ($products as $product) {
                $sheet->fromArray([
                    $product->getName(),
                    $product->getCategory()?->getName() ?? '',
                    $product->getStockQuantity(),
                    '',
                    '',
                ], null, 'A'.$row);
                ++$row;
            }
            $lastProductRow = max(2, $row - 1);
            $sheet->getStyle('B2:C'.$lastProductRow)->getFont()->setItalic(true);
            $sheet->getStyle('B2:C'.$lastProductRow)->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
            $sheet->getStyle('A2:A'.$lastProductRow)->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
            $sheet->getStyle('D2:E'.$lastProductRow)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            $sheet->getStyle('B1:C'.$lastProductRow)->getAlignment()->setHorizontal('left');
            $sheet->getStyle('C2:C'.$lastProductRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A1:E'.$lastProductRow)->getBorders()->getAllBorders()->setBorderStyle('thin');
        }

        foreach (range(1, count($headers)) as $index) $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);

        $example = $spreadsheet->createSheet();
        $example->setTitle('Example');
        $example->fromArray($headers, null, 'A1');
        if ($type === ImportType::STOCK) {
            $firstProduct = $this->products->findAllActiveOrderedByName()[0] ?? null;
            $example->fromArray([
                $firstProduct?->getName() ?? 'Bia 333',
                $firstProduct?->getCategory()?->getName() ?? 'Bia',
                $firstProduct?->getStockQuantity() ?? 0,
                '10',
                'Initial stock - DELETE THIS SAMPLE ROW BEFORE IMPORT',
            ], null, 'A2');
        } else {
            $example->fromArray($sample, null, 'A2');
        }
        $example->getStyle('A1:'.chr(64 + count($headers)).'1')->getFont()->setBold(true);
        foreach (range(1, count($headers)) as $index) $example->getColumnDimensionByColumn($index)->setAutoSize(true);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $lines = match ($type) {
            ImportType::PRODUCT => [
                ['Template version', ImportWorkbookReader::PRODUCT_VERSION],
                ['Required format', '.xlsx'],
                ['Rule', 'Do not rename or reorder headers.'],
                ['Rule', 'SKU is optional. Leave it blank to auto-generate a stable SKU from Category + Name.'],
                ['Rule', 'If Category exists (case-insensitive), the existing active category is reused; otherwise a new category is created.'],
                ['Rule', 'Remove the sample row before import.'],
                ['Rule', 'Selling Price and Cost Price are VND amounts.'],
                ['Rule', 'Auto-generated SKU is assigned once and does not change when the product name or category changes.'],
                ['Rule', 'Stock is not imported on this sheet. Use Stock Import for stock adjustments.'],
            ],
            ImportType::STOCK => [
                ['Template version', ImportWorkbookReader::STOCK_VERSION],
                ['Required format', '.xlsx'],
                ['Rule', 'Do not rename or reorder headers.'],
                ['Rule', 'Use Product Name to identify the product. Matching is case-insensitive; SKU is not used for stock import.'],
                ['Rule', 'Category (display only) is loaded from the current product catalog and is not imported.'],
                ['Rule', 'Current Stock (display only) is a snapshot at template download time and is not imported or used as the stock value.'],
                ['Rule', 'Only Quantity Change and Reason are imported. Quantity Change is a delta: +10 adds 10, -3 removes 3.'],
                ['Rule', 'Leave Quantity Change blank for products you do not want to adjust; those catalog rows are ignored.'],
                ['Rule', 'Stock can never become negative.'],
                ['Rule', 'The downloaded product list contains active products available at template download time.'],
            ],
            ImportType::CUSTOMER => [
                ['Template version', ImportWorkbookReader::CUSTOMER_VERSION],
                ['Required format', '.xlsx'],
                ['Rule', 'Do not rename or reorder headers.'],
                ['Rule', 'Name is required. Phone is optional but must be unique when provided.'],
                ['Rule', 'Default Discount Percent must be between 0 and 100.'],
                ['Rule', 'Delete the sample row before importing.'],
                ['Rule', 'Import creates customers only; it does not change orders, payments, debt, or other core business data.'],
            ],
        };
        $instructions->fromArray($lines, null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(90);

        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) throw new \RuntimeException('Unable to create template stream.');
        $writer->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();
        if (!is_string($contents)) throw new \RuntimeException('Unable to generate XLSX template.');
        return $contents;
    }
}
