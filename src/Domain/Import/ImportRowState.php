<?php

declare(strict_types=1);

namespace App\Domain\Import;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_row_states')]
#[ORM\UniqueConstraint(name: 'uq_import_row_batch_row', columns: ['import_batch_id', 'row_num'])]
#[ORM\Index(name: 'idx_import_row_batch_status', columns: ['import_batch_id', 'status'])]
class ImportRowState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ImportBatch::class)]
    #[ORM\JoinColumn(name: 'import_batch_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ImportBatch $batch;

    #[ORM\Column(name: 'row_num', type: 'integer')]
    private int $rowNumber;

    #[ORM\Column(length: 64)]
    private string $fingerprint;

    #[ORM\Column(name: 'payload_json', type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(enumType: ImportRowStatus::class, length: 20)]
    private ImportRowStatus $status = ImportRowStatus::PENDING;

    #[ORM\Column(name: 'result_id', type: 'bigint', nullable: true)]
    private ?int $resultId = null;

    #[ORM\Column(name: 'error_code', length: 100, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(name: 'error_message', length: 500, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(ImportBatch $batch, int $rowNumber, string $fingerprint, ?array $payload = null)
    {
        if ($rowNumber < 2) throw new \InvalidArgumentException('Import row number must be >= 2.');
        $this->batch = $batch;
        $this->rowNumber = $rowNumber;
        $this->fingerprint = $fingerprint;
        $this->payload = $payload;
    }
    public function getId(): ?int { return $this->id; }
    public function getBatch(): ImportBatch { return $this->batch; }
    public function getRowNumber(): int { return $this->rowNumber; }
    public function getFingerprint(): string { return $this->fingerprint; }
    /** @return array<string,string> */
    public function getPayload(): array { return $this->payload ?? []; }
    public function hasPayload(): bool { return $this->payload !== null; }
    /** @param array<string,string> $payload */
    public function applyPreview(array $payload, string $fingerprint, ?array $error = null): void
    {
        $this->payload = $payload;
        $this->fingerprint = $fingerprint;
        $this->resultId = null;
        $this->startedAt = null;
        $this->finishedAt = null;
        $this->errorCode = null;
        $this->errorMessage = null;
        if ($error === null) {
            $this->status = ImportRowStatus::PENDING;
            return;
        }
        $this->markFailed($error['code'], $error['message']);
    }
    public function getStatus(): ImportRowStatus { return $this->status; }
    public function getResultId(): ?int { return $this->resultId; }
    public function getErrorCode(): ?string { return $this->errorCode; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function markProcessing(): void { $this->status = ImportRowStatus::PROCESSING; $this->startedAt = new \DateTimeImmutable(); }
    public function markSuccess(?int $resultId = null): void { $this->status = ImportRowStatus::SUCCESS; $this->resultId = $resultId; $this->finishedAt = new \DateTimeImmutable(); }
    public function markFailed(string $code, string $message): void { $this->status = ImportRowStatus::FAILED; $this->errorCode = $code; $this->errorMessage = mb_substr(trim($message), 0, 500); $this->finishedAt = new \DateTimeImmutable(); }
}
