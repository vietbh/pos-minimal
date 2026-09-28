<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Import;

use App\Application\Import\Excel\ImportRowFingerprint;
use PHPUnit\Framework\TestCase;

final class ImportRowFingerprintTest extends TestCase
{
    public function testFingerprintIsStableWhenJsonObjectKeysAreReorderedByPersistence(): void
    {
        $fingerprint = new ImportRowFingerprint();

        $original = [
            'SKU' => 'BIA-333-LON',
            'Name' => 'Bia 333 lon',
            'Category' => 'Bia',
            'Unit' => 'lon',
            'Selling Price' => '11000',
            'Cost Price' => '10000',
            'Low Stock Threshold' => '5',
            'Note' => '',
        ];

        $reordered = [
            'Note' => '',
            'Low Stock Threshold' => '5',
            'Cost Price' => '10000',
            'Selling Price' => '11000',
            'Unit' => 'lon',
            'Category' => 'Bia',
            'Name' => 'Bia 333 lon',
            'SKU' => 'BIA-333-LON',
        ];

        self::assertSame($fingerprint->hash($original), $fingerprint->hash($reordered));
    }

    public function testFingerprintChangesWhenBusinessValueChanges(): void
    {
        $fingerprint = new ImportRowFingerprint();
        $row = ['SKU' => 'ABC', 'Name' => 'Product', 'Selling Price' => '10000'];
        $changed = ['SKU' => 'ABC', 'Name' => 'Product', 'Selling Price' => '11000'];

        self::assertNotSame($fingerprint->hash($row), $fingerprint->hash($changed));
    }
}
