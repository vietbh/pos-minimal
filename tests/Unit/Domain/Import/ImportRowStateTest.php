<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import;

use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowState;
use App\Domain\Import\ImportRowStatus;
use PHPUnit\Framework\TestCase;

final class ImportRowStateTest extends TestCase
{
    public function testPreviewPayloadIsPersistedAndRevalidationResetsProcessingState(): void
    {
        $batch = $this->createMock(ImportBatch::class);
        $state = new ImportRowState($batch, 2, hash('sha256', 'old'), ['Name' => 'Old']);
        $state->markProcessing();

        $payload = ['Name' => 'New', 'SKU' => 'ABC-001'];
        $state->applyPreview($payload, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));

        self::assertSame($payload, $state->getPayload());
        self::assertTrue($state->hasPayload());
        self::assertSame(ImportRowStatus::PENDING, $state->getStatus());
        self::assertNull($state->getResultId());
        self::assertNull($state->getErrorCode());
    }
}
