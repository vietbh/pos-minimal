<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Import;

use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\ImportType;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ImportStateTest extends TestCase
{
    public function testValidBatchCanBeQueued(): void
    {
        $user = new User('import-state-'.bin2hex(random_bytes(4)));
        $batch = new ImportBatch(ImportType::PRODUCT, $user, 'products.xlsx', 'imports/a.xlsx', 'product-v2');
        $batch->beginValidation();
        $batch->markValidationResult(2, 2, 0);
        self::assertSame(ImportStatus::READY, $batch->getStatus());
        $batch->confirmQueue();
        self::assertSame(ImportStatus::QUEUED, $batch->getStatus());
    }

    public function testInvalidRowsBlockConfirmation(): void
    {
        $user = new User('import-state-'.bin2hex(random_bytes(4)));
        $batch = new ImportBatch(ImportType::STOCK, $user, 'stock.xlsx', 'imports/b.xlsx', 'stock-v2');
        $batch->beginValidation();
        $batch->markValidationResult(2, 1, 1);
        self::assertSame(ImportStatus::VALIDATION_FAILED, $batch->getStatus());
        $this->expectException(\DomainException::class);
        $batch->confirmQueue();
    }
}
