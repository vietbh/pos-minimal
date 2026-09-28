<?php

declare(strict_types=1);

namespace App\Application\Import\Message;

use App\Application\Common\Storage\FileStorageInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Import\Excel\ImportWorkbookReader;
use App\Application\Import\Excel\ImportRowFingerprint;
use App\Application\Product\Command\AdjustStock\AdjustStockHandlerEntryPoint;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Product\Command\CreateProduct\CreateProductHandler;
use App\Application\Product\Command\CreateProduct\CreateProductInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowState;
use App\Domain\Import\ImportRowStatus;
use App\Domain\Import\ImportType;
use App\Domain\Import\Repository\ImportBatchRepositoryInterface;
use App\Domain\Import\Repository\ImportRowStateRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessImportHandler
{
    public function __construct(
        private ImportBatchRepositoryInterface $batches,
        private ImportRowStateRepositoryInterface $rowStates,
        private ImportWorkbookReader $reader,
        private ImportRowFingerprint $fingerprints,
        private FileStorageInterface $storage,
        private CreateProductHandler $createProduct,
        private CreateCustomerHandler $createCustomer,
        private AdjustStockHandlerEntryPoint $adjustStock,
        private ProductRepositoryInterface $products,
        private CustomerRepositoryInterface $customers,
        private RuntimeActorContextProvider $actorContext,
        private TransactionManagerInterface $transactions,
    ) {}

    public function __invoke(ProcessImport $message): void
    {
        $batchId = $message->importBatchId;
        $batch = $this->batches->findById($batchId);
        if (!$batch instanceof ImportBatch) {
            throw new \RuntimeException('Import batch was not found.');
        }

        if (in_array($batch->getStatus()->value, ['COMPLETED', 'PARTIAL', 'FAILED'], true)) {
            return;
        }
        if (!in_array($batch->getStatus()->value, ['READY', 'QUEUED', 'PROCESSING'], true)) {
            return;
        }

        // Read the actor id while the requester proxy is managed. The transaction
        // manager deliberately clears the EntityManager after a rollback, so no
        // User proxy may be dereferenced after a nested transaction failure.
        $userId = $batch->getRequestedBy()->getId();
        if ($userId === null) {
            throw new \LogicException('Import requester has no ID.');
        }

        if ($batch->getStatus()->value === 'READY') {
            $this->transactions->run(function ($tx) use ($batchId): void {
                $fresh = $this->batches->findById($batchId);
                if (!$fresh instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');
                if ($fresh->getStatus()->value !== 'READY') return;
                $fresh->confirmQueue();
                $fresh->beginProcessing();
                $this->batches->save($fresh);
                $tx->flush();
            });
        } elseif ($batch->getStatus()->value === 'QUEUED') {
            $this->transactions->run(function ($tx) use ($batchId): void {
                $fresh = $this->batches->findById($batchId);
                if (!$fresh instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');
                if ($fresh->getStatus()->value !== 'QUEUED') return;
                $fresh->beginProcessing();
                $this->batches->save($fresh);
                $tx->flush();
            });
        }

        $this->actorContext->set(new ActorContext($userId, null, $batch->getRequestId()));
        try {
            $currentBatch = $this->batches->findById($batchId);
            if (!$currentBatch instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');

            $invalidRows = array_fill_keys($this->rowStates->findErrorRowNumbers($currentBatch), true);
            $states = $this->rowStates->findByBatch($currentBatch);
            $legacyRows = null;

            foreach ($states as $state) {
                $rowNumber = $state->getRowNumber();
                if (isset($invalidRows[$rowNumber])) continue;

                $row = $state->hasPayload() ? $state->getPayload() : null;
                if ($row === null) {
                    // Backward compatibility for batches created before preview payloads existed.
                    $legacyRows ??= iterator_to_array(
                        $this->reader->rows(
                            $this->storage->path($currentBatch->getStorageKey()),
                            $currentBatch->getType(),
                        ),
                    );
                    $row = $legacyRows[$rowNumber] ?? null;
                }
                if ($row === null) continue;

                // Do not carry Doctrine entities across row transactions. A failed
                // product/stock transaction clears the EntityManager; the next row
                // must therefore resolve fresh managed entities by id.
                $this->processRow($batchId, $rowNumber, $row);
            }

            $this->transactions->run(function ($tx) use ($batchId): void {
                $fresh = $this->batches->findById($batchId);
                if (!$fresh instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');
                $fresh->finish();
                $this->batches->save($fresh);
                $tx->flush();
            });
        } catch (\Throwable $e) {
            // The failing transaction may already have cleared the EntityManager.
            // Rehydrate the batch before mutating it so its requestedBy association
            // points to a managed proxy with a valid UnitOfWork identifier.
            try {
                $this->transactions->run(function ($tx) use ($batchId, $e): void {
                    $fresh = $this->batches->findById($batchId);
                    if (!$fresh instanceof ImportBatch) return;
                    $fresh->recordError('IMPORT_PROCESSING_FAILED', $this->safeMessage($e));
                    $fresh->fail();
                    $this->batches->save($fresh);
                    $tx->flush();
                });
            } catch (\Throwable) {
                // Preserve the original processing exception for Messenger retry/dead-letter handling.
            }
            throw $e;
        } finally {
            $this->actorContext->clear();
        }
    }

    /** @param array<string,string> $row */
    private function processRow(int $batchId, int $rowNumber, array $row): void
    {
        $fingerprint = $this->fingerprints->hash($row);
        $stateId = $this->transactions->run(function ($tx) use ($batchId, $rowNumber): ?int {
            $batch = $this->batches->findById($batchId);
            if (!$batch instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');
            $state = $this->rowStates->findForUpdate($batch, $rowNumber);
            if ($state === null) return null;
            if ($state->getStatus() === ImportRowStatus::SUCCESS || $state->getStatus() === ImportRowStatus::FAILED) return null;
            if ($state->getStatus() === ImportRowStatus::PROCESSING && $state->getStartedAt() !== null && $state->getStartedAt() > new \DateTimeImmutable('-10 minutes')) return null;
            $state->markProcessing();
            $this->rowStates->save($state);
            $tx->flush();
            return $state->getId();
        });

        if ($stateId === null) return;

        // The previous transaction may have returned a managed entity, but the
        // product/stock handler below owns another transaction and may clear the
        // EntityManager on failure. Only scalar ids cross that boundary.
        $state = $this->rowStates->findById($stateId);
        if (!$state instanceof ImportRowState) throw new \RuntimeException('Import row state was not found.');
        if (!hash_equals($state->getFingerprint(), $fingerprint)) {
            $this->finishRow($batchId, $stateId, false, 'IMPORT_ROW_CHANGED', 'The workbook row changed after validation.');
            return;
        }

        try {
            $batch = $this->batches->findById($batchId);
            if (!$batch instanceof ImportBatch) throw new \RuntimeException('Import batch was not found.');
            $resultId = match ($batch->getType()) {
                ImportType::PRODUCT => $this->processProduct($row),
                ImportType::STOCK => $this->processStock($row, $batchId, $rowNumber),
                ImportType::CUSTOMER => $this->processCustomer($row),
            };
            $this->finishRow($batchId, $stateId, true, null, null, $resultId);
        } catch (\Throwable $e) {
            if (!$e instanceof \InvalidArgumentException && !$e instanceof \DomainException) {
                throw $e;
            }
            $this->finishRow($batchId, $stateId, false, $this->errorCode($e), $this->safeMessage($e));
        }
    }

    /** @param array<string,string> $row */
    private function processProduct(array $row): ?int
    {
        $skuValue = trim($row['SKU']);
        $sku = $skuValue !== '' ? new Sku($skuValue) : null;
        try {
            $id = ($this->createProduct)(new CreateProductInput(
                name: trim($row['Name']),
                sellingPrice: Money::fromDecimal(trim($row['Selling Price'])),
                sku: $sku,
                unit: trim($row['Unit']) !== '' ? trim($row['Unit']) : null,
                costPrice: trim($row['Cost Price']) !== '' ? Money::fromDecimal(trim($row['Cost Price'])) : null,
                lowStockThreshold: (int) trim($row['Low Stock Threshold']),
                note: trim($row['Note']) !== '' ? trim($row['Note']) : null,
                categoryName: trim($row['Category']) !== '' ? trim($row['Category']) : null,
            ));
            return $id;
        } catch (\DomainException $e) {
            if ($sku !== null) {
                $existing = $this->products->findBySku($sku);
                if ($existing !== null && $this->productMatchesRow($existing, $row)) return $existing->getId();
            }
            throw $e;
        }
    }

    /** @param array<string,string> $row */
    private function processCustomer(array $row): ?int
    {
        $phone = trim($row['Phone'] ?? '');
        try {
            return ($this->createCustomer)(new CreateCustomerInput(
                name: trim($row['Name'] ?? ''),
                phone: $phone !== '' ? $phone : null,
                note: trim($row['Note'] ?? '') !== '' ? trim($row['Note']) : null,
                defaultDiscountPercent: (int) trim($row['Default Discount Percent'] ?? '0'),
            ));
        } catch (\DomainException $e) {
            if ($phone !== '') {
                $existing = $this->customers->findByPhone($phone);
                if ($existing !== null
                    && $existing->getName() === trim($row['Name'] ?? '')
                    && $existing->getDefaultDiscountPercent() === (int) trim($row['Default Discount Percent'] ?? '0')
                    && $existing->getNote() === (trim($row['Note'] ?? '') !== '' ? trim($row['Note']) : null)
                ) {
                    return $existing->getId();
                }
            }
            throw $e;
        }
    }

    /** @param array<string,string> $row */
    private function processStock(array $row, int $batchId, int $rowNumber): ?int
    {
        $product = $this->products->findByName(trim($row['Product Name'] ?? ''));
        if ($product === null || $product->getId() === null) throw new \DomainException('Product was not found.');
        $result = $this->adjustStock->handle(new AdjustStockInput(
            $product->getId(),
            (int) trim($row['Quantity Change']),
            trim($row['Reason']) !== '' ? trim($row['Reason']) : null,
            'import:'.$batchId.':row:'.$rowNumber,
        ));
        return $result->stockMovementId;
    }

    private function finishRow(int $batchId, int $stateId, bool $success, ?string $code, ?string $message, ?int $resultId = null): void
    {
        $this->transactions->run(function ($tx) use ($batchId, $stateId, $success, $code, $message, $resultId): void {
            $batch = $this->batches->findById($batchId);
            $state = $this->rowStates->findById($stateId);
            if (!$batch instanceof ImportBatch || !$state instanceof ImportRowState) {
                throw new \RuntimeException('Import state could not be reloaded.');
            }
            if ($success) $state->markSuccess($resultId);
            else $state->markFailed($code ?? 'IMPORT_ROW_PROCESSING_FAILED', $message ?? 'Row processing failed.');
            $this->rowStates->save($state);
            $batch->markRowProcessed($success);
            $this->batches->save($batch);
            $tx->flush();
        });
    }

    /** @param array<string,string> $row */
    private function productMatchesRow(\App\Domain\Product\Product $product, array $row): bool
    {
        $categoryName = trim($row['Category'] ?? '');
        return $product->getName() === trim($row['Name'])
            && $product->getSellingPrice()->equals(Money::fromDecimal(trim($row['Selling Price'])))
            && ($product->getCostPrice()?->equals(Money::fromDecimal(trim($row['Cost Price']))) ?? trim($row['Cost Price']) === '')
            && $product->getUnit() === (trim($row['Unit']) !== '' ? trim($row['Unit']) : null)
            && $product->getLowStockThreshold() === (int) trim($row['Low Stock Threshold'])
            && $product->getNote() === (trim($row['Note']) !== '' ? trim($row['Note']) : null)
            && mb_strtolower(trim($product->getCategory()?->getName() ?? '')) === mb_strtolower($categoryName);
    }

    private function errorCode(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) return 'IMPORT_ROW_VALIDATION_FAILED';
        if ($e instanceof \DomainException) {
            if (str_contains(strtolower($e->getMessage()), 'insufficient stock')) return 'IMPORT_STOCK_NEGATIVE';
            if (str_contains(strtolower($e->getMessage()), 'customer with this phone') || str_contains(strtolower($e->getMessage()), 'customer already exists')) return 'IMPORT_CUSTOMER_ALREADY_EXISTS';
            if (str_contains(strtolower($e->getMessage()), 'already exists')) return 'IMPORT_PRODUCT_ALREADY_EXISTS';
            return 'IMPORT_ROW_DOMAIN_FAILED';
        }
        return 'IMPORT_ROW_PROCESSING_FAILED';
    }

    private function safeMessage(\Throwable $e): string
    {
        return mb_substr(trim($e->getMessage()), 0, 500);
    }
}
