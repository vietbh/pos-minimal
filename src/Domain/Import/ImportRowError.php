<?php

declare(strict_types=1);

namespace App\Domain\Import;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_row_errors')]
#[ORM\Index(name: 'idx_import_error_batch_row', columns: ['import_batch_id', 'row_num'])]
class ImportRowError
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

    #[ORM\Column(length: 100)]
    private string $field;

    #[ORM\Column(name: 'error_code', length: 100)]
    private string $errorCode;

    #[ORM\Column(length: 500)]
    private string $message;

    #[ORM\Column(name: 'raw_value', type: 'text', nullable: true)]
    private ?string $rawValue;

    public function __construct(ImportBatch $batch, int $rowNumber, string $field, string $errorCode, string $message, ?string $rawValue = null)
    {
        $this->batch = $batch; $this->rowNumber = $rowNumber; $this->field = trim($field); $this->errorCode = trim($errorCode); $this->message = mb_substr(trim($message), 0, 500); $this->rawValue = $rawValue !== null ? mb_substr($rawValue, 0, 2000) : null;
    }
    public function getId(): ?int { return $this->id; }
    public function getRowNumber(): int { return $this->rowNumber; }
    public function getField(): string { return $this->field; }
    public function getErrorCode(): string { return $this->errorCode; }
    public function getMessage(): string { return $this->message; }
    public function getRawValue(): ?string { return $this->rawValue; }
}
