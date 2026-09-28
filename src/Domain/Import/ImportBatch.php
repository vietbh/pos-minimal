<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_batches')]
#[ORM\Index(name: 'idx_import_batch_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_import_batch_user_created', columns: ['requested_by_user_id', 'created_at'])]
class ImportBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(enumType: ImportType::class, length: 20)]
    private ImportType $type;

    #[ORM\Column(enumType: ImportStatus::class, length: 30)]
    private ImportStatus $status;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requested_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $requestedBy;

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'storage_key', length: 500)]
    private string $storageKey;

    #[ORM\Column(name: 'template_version', length: 30)]
    private string $templateVersion;

    #[ORM\Column(name: 'request_id', length: 100, nullable: true)]
    private ?string $requestId;

    #[ORM\Column(name: 'total_rows', type: 'integer')]
    private int $totalRows = 0;

    #[ORM\Column(name: 'valid_rows', type: 'integer')]
    private int $validRows = 0;

    #[ORM\Column(name: 'invalid_rows', type: 'integer')]
    private int $invalidRows = 0;

    #[ORM\Column(name: 'processed_rows', type: 'integer')]
    private int $processedRows = 0;

    #[ORM\Column(name: 'success_rows', type: 'integer')]
    private int $successRows = 0;

    #[ORM\Column(name: 'failed_rows', type: 'integer')]
    private int $failedRows = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'error_code', length: 100, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(name: 'error_message', length: 1000, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(
        ImportType $type,
        User $requestedBy,
        string $originalFilename,
        string $storageKey,
        string $templateVersion,
        ?string $requestId = null,
    ) {
        $this->type = $type;
        $this->status = ImportStatus::UPLOADED;
        $this->requestedBy = $requestedBy;
        $this->originalFilename = trim($originalFilename);
        $this->storageKey = trim($storageKey);
        $this->templateVersion = trim($templateVersion);
        $this->requestId = $requestId !== null ? trim($requestId) ?: null : null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): ImportType { return $this->type; }
    public function getStatus(): ImportStatus { return $this->status; }
    public function getRequestedBy(): User { return $this->requestedBy; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function getStorageKey(): string { return $this->storageKey; }
    public function getTemplateVersion(): string { return $this->templateVersion; }
    public function getRequestId(): ?string { return $this->requestId; }
    public function getTotalRows(): int { return $this->totalRows; }
    public function getValidRows(): int { return $this->validRows; }
    public function getInvalidRows(): int { return $this->invalidRows; }
    public function getProcessedRows(): int { return $this->processedRows; }
    public function getSuccessRows(): int { return $this->successRows; }
    public function getFailedRows(): int { return $this->failedRows; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function getErrorCode(): ?string { return $this->errorCode; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }

    public function recordError(string $code, string $message): void
    {
        $this->errorCode = trim($code) !== '' ? trim($code) : 'IMPORT_PROCESSING_FAILED';
        $this->errorMessage = mb_substr(trim($message), 0, 1000);
        $this->touch();
    }

    public function beginValidation(): void { $this->clearError(); $this->transition(ImportStatus::VALIDATING); }
    public function beginRevalidation(): void { $this->clearError(); $this->transition(ImportStatus::VALIDATING); }
    public function markValidationResult(int $total, int $valid, int $invalid): void
    {
        $this->totalRows = max(0, $total);
        $this->validRows = max(0, $valid);
        $this->invalidRows = max(0, $invalid);
        $this->transition($invalid > 0 ? ImportStatus::VALIDATION_FAILED : ImportStatus::READY);
    }
    public function confirmQueue(): void { $this->clearError(); $this->transition(ImportStatus::QUEUED); }
    public function beginProcessing(): void
    {
        $this->transition(ImportStatus::PROCESSING);
        $this->startedAt ??= new \DateTimeImmutable();
    }
    public function markRowProcessed(bool $success): void
    {
        ++$this->processedRows;
        $success ? ++$this->successRows : ++$this->failedRows;
        $this->touch();
    }
    public function finish(): void
    {
        $this->status = $this->failedRows > 0 ? ImportStatus::PARTIAL : ImportStatus::COMPLETED;
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }
    public function fail(): void
    {
        $this->status = ImportStatus::FAILED;
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    private function clearError(): void { $this->errorCode = null; $this->errorMessage = null; }

    private function transition(ImportStatus $next): void
    {
        $allowed = match ($this->status) {
            ImportStatus::UPLOADED => [ImportStatus::VALIDATING],
            ImportStatus::VALIDATION_FAILED => [ImportStatus::VALIDATING],
            ImportStatus::READY => [ImportStatus::VALIDATING, ImportStatus::QUEUED],
            ImportStatus::VALIDATING => [ImportStatus::VALIDATION_FAILED, ImportStatus::READY, ImportStatus::FAILED],
            ImportStatus::READY => [ImportStatus::QUEUED],
            ImportStatus::QUEUED => [ImportStatus::PROCESSING],
            ImportStatus::PROCESSING => [ImportStatus::COMPLETED, ImportStatus::PARTIAL, ImportStatus::FAILED],
            default => [],
        };
        if (!in_array($next, $allowed, true)) {
            throw new \DomainException(sprintf('Invalid import state transition: %s -> %s.', $this->status->value, $next->value));
        }
        $this->status = $next;
        $this->touch();
    }
}
