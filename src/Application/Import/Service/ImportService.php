<?php

declare(strict_types=1);

namespace App\Application\Import\Service;

use App\Application\Common\Storage\FileStorageInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Import\Excel\ImportRowValidator;
use App\Application\Import\Excel\ImportRowFingerprint;
use App\Application\Import\Excel\ImportTemplateGenerator;
use App\Application\Import\Excel\ImportWorkbookReader;
use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowError;
use App\Domain\Import\ImportRowState;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\ImportType;
use App\Domain\Import\Repository\ImportBatchRepositoryInterface;
use App\Domain\Import\Repository\ImportRowErrorRepositoryInterface;
use App\Domain\Import\Repository\ImportRowStateRepositoryInterface;
use App\Domain\User\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportService
{
    public function __construct(
        private FileStorageInterface $storage,
        private ImportBatchRepositoryInterface $batches,
        private ImportRowStateRepositoryInterface $rowStates,
        private ImportRowErrorRepositoryInterface $errors,
        private ImportWorkbookReader $reader,
        private ImportRowValidator $validator,
        private ImportRowFingerprint $fingerprints,
        private ImportTemplateGenerator $templates,
        private TransactionManagerInterface $transactions,
    ) {}

    public function template(ImportType $type): string
    {
        return $this->templates->generate($type);
    }

    /** @return list<string> */
    public function previewHeaders(ImportType $type): array
    {
        return $this->reader->headers($type);
    }

    public function upload(UploadedFile $file, ImportType $type, User $user, ?string $requestId, int $maxRows, int $maxBytes): ImportBatch
    {
        $this->validateFile($file, $maxBytes);
        $storageKey = 'imports/'.bin2hex(random_bytes(16)).'.xlsx';
        $this->storage->putFile($file->getPathname(), $storageKey);
        try {
            $batch = new ImportBatch($type, $user, $file->getClientOriginalName() ?: 'import.xlsx', $storageKey, $this->reader->version($type), $requestId);
            $this->transactions->run(function ($tx) use ($batch): void {
                $this->batches->save($batch);
                $tx->flush();
            });
            $this->validateBatch($batch, $maxRows);
            return $batch;
        } catch (\Throwable $e) {
            try { $this->storage->delete($storageKey); } catch (\Throwable) {}
            throw $e;
        }
    }

    /** @return list<ImportRowState> */
    public function previewRows(ImportBatch $batch): array
    {
        return $this->rowStates->findByBatch($batch);
    }

    /** @return array<int,array<string,string>> */
    public function previewPayloads(ImportBatch $batch): array
    {
        $states = $this->rowStates->findByBatch($batch);
        $payloads = [];
        $needsLegacyRead = false;
        foreach ($states as $state) {
            if ($state->hasPayload()) {
                $payloads[$state->getRowNumber()] = $state->getPayload();
            } else {
                $needsLegacyRead = true;
            }
        }
        if ($needsLegacyRead) {
            foreach ($this->reader->rows($this->storage->path($batch->getStorageKey()), $batch->getType()) as $rowNumber => $row) {
                $payloads[(int) $rowNumber] = $row;
            }
        }
        return $payloads;
    }

    /**
     * Persist the edited preview rows, re-run the exact same row validation,
     * and move the batch back to READY only when every row is valid.
     *
     * @param array<int,array<string,string>> $rows
     */
    public function savePreview(ImportBatch $batch, array $rows): void
    {
        if (!in_array($batch->getStatus(), [ImportStatus::READY, ImportStatus::VALIDATION_FAILED], true)) {
            throw new \DomainException('Import preview can only be edited before confirmation.');
        }

        $states = $this->rowStates->findByBatch($batch);
        if ($states === []) {
            throw new \DomainException('Import contains no preview rows.');
        }
        $stateByRow = [];
        foreach ($states as $state) $stateByRow[$state->getRowNumber()] = $state;

        $normalizedRows = [];
        foreach ($rows as $rowNumber => $row) {
            $rowNumber = (int) $rowNumber;
            if (!isset($stateByRow[$rowNumber]) || !is_array($row)) {
                throw new \InvalidArgumentException('IMPORT_PREVIEW_INVALID_ROW: Preview contains an unknown row.');
            }
            $normalized = [];
            foreach ($this->reader->headers($batch->getType()) as $header) {
                $value = $row[$header] ?? '';
                if (is_array($value)) throw new \InvalidArgumentException('IMPORT_PREVIEW_INVALID_VALUE: Invalid preview value.');
                $normalized[$header] = trim((string) $value);
            }
            $normalizedRows[$rowNumber] = $normalized;
        }
        if (count($normalizedRows) !== count($stateByRow) || array_diff_key($stateByRow, $normalizedRows) !== []) {
            throw new \InvalidArgumentException('IMPORT_PREVIEW_INCOMPLETE: Please keep every imported row in the preview.');
        }

        $batch->beginRevalidation();
        $seen = [];
        $results = [];
        foreach ($normalizedRows as $rowNumber => $row) {
            $errors = $this->validator->validate($batch->getType(), $row);
            $identity = $this->rowIdentity($batch->getType(), $row);
            if ($identity !== '') {
                $normalizedIdentity = mb_strtolower($identity);
                if (isset($seen[$normalizedIdentity])) {
                    $field = $this->rowIdentityField($batch->getType());
                        $errors[] = ['field' => $field, 'code' => 'IMPORT_DUPLICATE_ROW', 'message' => $field.' is duplicated inside the import file.', 'value' => $identity];
                } else {
                    $seen[$normalizedIdentity] = true;
                }
            }
            $results[$rowNumber] = [$row, $errors];
        }

        $total = count($results);
        $invalid = 0;
        foreach ($results as [$row, $errors]) if ($errors !== []) ++$invalid;
        $valid = $total - $invalid;

        $this->transactions->run(function ($tx) use ($batch, $states, $results, $valid, $invalid): void {
            $this->errors->deleteByBatch($batch);
            foreach ($states as $state) {
                [$row, $errors] = $results[$state->getRowNumber()];
                $fingerprint = $this->fingerprints->hash($row);
                $state->applyPreview($row, $fingerprint, $errors === [] ? null : [
                    'code' => $errors[0]['code'],
                    'message' => $errors[0]['message'],
                ]);
                $this->rowStates->save($state);
                foreach ($errors as $error) {
                    $this->errors->save(new ImportRowError($batch, $state->getRowNumber(), $error['field'], $error['code'], $error['message'], $error['value']));
                }
            }
            $batch->markValidationResult(count($results), $valid, $invalid);
            $this->batches->save($batch);
            $tx->flush();
        });
    }

    public function validateBatch(ImportBatch $batch, int $maxRows): void
    {
        $batch->beginValidation();
        $this->transactions->run(function ($tx) use ($batch): void { $this->batches->save($batch); $tx->flush(); });
        $total = 0; $valid = 0; $invalid = 0; $seen = [];
        try {
            foreach ($this->reader->rows($this->storage->path($batch->getStorageKey()), $batch->getType()) as $rowNumber => $row) {
                ++$total;
                if ($total > $maxRows) throw new \InvalidArgumentException('IMPORT_TOO_MANY_ROWS: Import exceeds the configured row limit.');
                $fingerprint = $this->fingerprints->hash($row);
                $errors = $this->validator->validate($batch->getType(), $row);
                $identity = $this->rowIdentity($batch->getType(), $row);
                if ($identity !== '') {
                    $normalizedIdentity = mb_strtolower($identity);
                    if (isset($seen[$normalizedIdentity])) {
                        $field = $this->rowIdentityField($batch->getType());
                        $errors[] = ['field' => $field, 'code' => 'IMPORT_DUPLICATE_ROW', 'message' => $field.' is duplicated inside the import file.', 'value' => $identity];
                    } else { $seen[$normalizedIdentity] = true; }
                }
                $this->transactions->run(function ($tx) use ($batch, $rowNumber, $row, $fingerprint, $errors): void {
                    $state = new ImportRowState($batch, (int) $rowNumber, $fingerprint, $row);
                    if ($errors !== []) {
                        $first = $errors[0];
                        $state->markFailed($first['code'], $first['message']);
                    }
                    $this->rowStates->save($state);
                    foreach ($errors as $error) {
                        $this->errors->save(new ImportRowError($batch, (int) $rowNumber, $error['field'], $error['code'], $error['message'], $error['value']));
                    }
                    $tx->flush();
                });
                if ($errors === []) ++$valid; else ++$invalid;
            }
            if ($total === 0) throw new \InvalidArgumentException('IMPORT_INVALID_TEMPLATE: The workbook contains no data rows.');
            $this->transactions->run(function ($tx) use ($batch, $total, $valid, $invalid): void {
                $batch->markValidationResult($total, $valid, $invalid);
                $this->batches->save($batch);
                $tx->flush();
            });
        } catch (\Throwable $e) {
            $batch->fail();
            try { $this->transactions->run(function ($tx) use ($batch): void { $this->batches->save($batch); $tx->flush(); }); } catch (\Throwable) {}
            throw $e;
        }
    }

    /** @param array<string,string> $row */
    private function rowIdentity(ImportType $type, array $row): string
    {
        return match ($type) {
            ImportType::PRODUCT => trim($row['SKU'] ?? '') !== '' ? trim($row['SKU']) : mb_strtolower(trim($row['Name'] ?? '')),
            ImportType::STOCK => mb_strtolower(trim($row['Product Name'] ?? '')),
            ImportType::CUSTOMER => trim($row['Phone'] ?? '') !== '' ? trim($row['Phone']) : mb_strtolower(trim($row['Name'] ?? '')),
        };
    }

    private function rowIdentityField(ImportType $type): string
    {
        return match ($type) { ImportType::PRODUCT => 'SKU/Name', ImportType::STOCK => 'Product Name', ImportType::CUSTOMER => 'Phone/Name' };
    }

    private function validateFile(UploadedFile $file, int $maxBytes): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) throw new \InvalidArgumentException('IMPORT_INVALID_FILE: Upload failed.');
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') throw new \InvalidArgumentException('IMPORT_INVALID_FILE: Only .xlsx files are supported.');
        if ($file->getSize() !== null && $file->getSize() > $maxBytes) throw new \InvalidArgumentException('IMPORT_INVALID_FILE: File exceeds the configured size limit.');
        $mime = $file->getMimeType();
        if ($mime !== null && !in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true)) throw new \InvalidArgumentException('IMPORT_INVALID_FILE: Unsupported workbook MIME type.');
    }
}
