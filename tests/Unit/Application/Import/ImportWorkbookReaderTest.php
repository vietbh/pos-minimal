<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Import;

use App\Application\Import\Excel\ImportWorkbookReader;
use App\Domain\Import\ImportType;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ImportWorkbookReaderTest extends TestCase
{
    public function testProductHeadersAreStable(): void
    {
        $reader = new ImportWorkbookReader();
        self::assertSame(['SKU', 'Name', 'Category', 'Unit', 'Selling Price', 'Cost Price', 'Low Stock Threshold', 'Note'], $reader->headers(ImportType::PRODUCT));
        self::assertSame('product-v2', $reader->version(ImportType::PRODUCT));
    }


    public function testProductWorkbookWithFormattedColumnsBeyondImportContractIsAccepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'import-reader-');
        self::assertNotFalse($path);
        $path .= '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ImportWorkbookReader::PRODUCT_SHEET);
            $headers = ['SKU', 'Name', 'Category', 'Unit', 'Selling Price', 'Cost Price', 'Low Stock Threshold', 'Note'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray([null, 'Bia Tiger lùn bạc lon', 'Bia', 'Lon', 13000, 15000, 5, null], null, 'A2');
            $sheet->getColumnDimension('Z')->setWidth(8);

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $reader = new ImportWorkbookReader();
            $rows = iterator_to_array($reader->rows($path, ImportType::PRODUCT));

            self::assertCount(1, $rows);
            self::assertSame('Bia Tiger lùn bạc lon', $rows[2]['Name']);
            self::assertSame('Bia', $rows[2]['Category']);
            self::assertSame('13000', $rows[2]['Selling Price']);
            self::assertSame('15000', $rows[2]['Cost Price']);
        } finally {
            @unlink($path);
        }
    }


    public function testStockWorkbookUsesProductNameAsIdentifier(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'import-stock-reader-');
        self::assertNotFalse($path);
        $path .= '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ImportWorkbookReader::STOCK_SHEET);
            $sheet->fromArray(['Product Name', 'Quantity Change', 'Reason'], null, 'A1');
            $sheet->fromArray(['Bia Tiger lùn bạc lon', 10, 'Nhập kho'], null, 'A2');

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $reader = new ImportWorkbookReader();
            $rows = iterator_to_array($reader->rows($path, ImportType::STOCK));

            self::assertSame('Bia Tiger lùn bạc lon', $rows[2]['Product Name']);
            self::assertSame('10', $rows[2]['Quantity Change']);
            self::assertSame('Nhập kho', $rows[2]['Reason']);
            self::assertArrayNotHasKey('SKU', $rows[2]);
        } finally {
            @unlink($path);
        }
    }

    public function testStockCatalogTemplateIgnoresDisplayOnlyColumnsAndBlankAdjustments(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'import-stock-catalog-');
        self::assertNotFalse($path);
        $path .= '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ImportWorkbookReader::STOCK_SHEET);
            $sheet->fromArray(['Product Name', 'Category (display only)', 'Current Stock (display only)', 'Quantity Change', 'Reason'], null, 'A1');
            $sheet->fromArray(['Bia Tiger lùn bạc lon', 'Bia', 25, '', ''], null, 'A2');
            $sheet->fromArray(['Bia Tiger lùn bạc thùng', 'Bia', 10, -3, 'Xuất kho'], null, 'A3');

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $reader = new ImportWorkbookReader();
            $rows = iterator_to_array($reader->rows($path, ImportType::STOCK));

            self::assertCount(1, $rows);
            self::assertSame('Bia Tiger lùn bạc thùng', $rows[3]['Product Name']);
            self::assertSame('-3', $rows[3]['Quantity Change']);
            self::assertSame('Xuất kho', $rows[3]['Reason']);
            self::assertArrayNotHasKey('Category (display only)', $rows[3]);
            self::assertArrayNotHasKey('Current Stock (display only)', $rows[3]);
        } finally {
            @unlink($path);
        }
    }

    public function testStockHeadersAreStable(): void
    {
        $reader = new ImportWorkbookReader();
        self::assertSame(['Product Name', 'Quantity Change', 'Reason'], $reader->headers(ImportType::STOCK));
        self::assertSame('stock-v3', $reader->version(ImportType::STOCK));
    }
}
