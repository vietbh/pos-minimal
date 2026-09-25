<?php
declare(strict_types=1);
namespace App\Domain\Payment;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_bank_accounts')]
#[ORM\Index(name: 'idx_payment_bank_account_active', columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN', columns: ['webhook_token'])]
class PaymentBankAccount
{
    #[ORM\Id, ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'bank_bin', length: 20)]
    private string $bankBin;
    #[ORM\Column(name: 'bank_name', length: 120)]
    private string $bankName;
    #[ORM\Column(name: 'account_number', length: 80)]
    private string $accountNumber;
    #[ORM\Column(name: 'account_name', length: 160)]
    private string $accountName;
    #[ORM\Column(name: 'qr_template', length: 30)]
    private string $qrTemplate;
    #[ORM\Column(name: 'transfer_content_template', length: 255)]
    private string $transferContentTemplate;
    #[ORM\Column(name: 'casso_sub_account_id', length: 80, nullable: true)]
    private ?string $cassoSubAccountId;
    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive;
    #[ORM\Column(name: 'webhook_token', length: 64)]
    private string $webhookToken;
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $bankBin, string $bankName, string $accountNumber, string $accountName, string $qrTemplate = 'compact2', string $transferContentTemplate = 'Thanh {PAYMENT REFERENCE} BUIHOANGVIET', ?string $cassoSubAccountId = null, bool $isActive = true)
    {
        foreach ([$bankBin, $bankName, $accountNumber, $accountName] as $value) {
            if (trim($value) === '') throw new \InvalidArgumentException('Bank account fields cannot be empty.');
        }
        $this->bankBin=trim($bankBin); $this->bankName=trim($bankName); $this->accountNumber=trim($accountNumber);
        $this->accountName=trim($accountName); $this->qrTemplate=trim($qrTemplate) ?: 'compact2';
        $this->transferContentTemplate=trim($transferContentTemplate) ?: 'Thanh {PAYMENT REFERENCE} BUIHOANGVIET';
        $this->cassoSubAccountId=$cassoSubAccountId !== null ? trim($cassoSubAccountId) ?: null : null;
        $this->isActive=$isActive; $this->webhookToken=bin2hex(random_bytes(32)); $this->createdAt=new \DateTimeImmutable(); $this->updatedAt=$this->createdAt;
    }
    public function getId(): ?int { return $this->id; }
    public function getBankBin(): string { return $this->bankBin; }
    public function getBankName(): string { return $this->bankName; }
    public function getAccountNumber(): string { return $this->accountNumber; }
    public function getAccountName(): string { return $this->accountName; }
    public function getQrTemplate(): string { return $this->qrTemplate; }
    public function getTransferContentTemplate(): string { return $this->transferContentTemplate; }
    public function getCassoSubAccountId(): ?string { return $this->cassoSubAccountId; }
    public function isActive(): bool { return $this->isActive; }
    public function getWebhookToken(): string { return $this->webhookToken; }
    public function regenerateWebhookToken(): string { $this->webhookToken=bin2hex(random_bytes(32)); $this->updatedAt=new \DateTimeImmutable(); return $this->webhookToken; }
    public function update(string $bankBin,string $bankName,string $accountNumber,string $accountName,string $qrTemplate,string $transferContentTemplate,?string $cassoSubAccountId,bool $isActive): void {
        $this->bankBin=trim($bankBin); $this->bankName=trim($bankName); $this->accountNumber=trim($accountNumber); $this->accountName=trim($accountName);
        $this->qrTemplate=trim($qrTemplate) ?: 'compact2'; $this->transferContentTemplate=trim($transferContentTemplate) ?: 'Thanh {PAYMENT REFERENCE} BUIHOANGVIET';
        $this->cassoSubAccountId=$cassoSubAccountId !== null ? trim($cassoSubAccountId) ?: null : null; $this->isActive=$isActive; $this->updatedAt=new \DateTimeImmutable();
    }
    public function setActive(bool $active): void { $this->isActive=$active; $this->updatedAt=new \DateTimeImmutable(); }
    public function transferContent(string $paymentReference): string
    {
        $content = str_replace(
            ['{PAYMENT REFERENCE}', '{ORDER NUMBER}', '{ORDER}'],
            [$paymentReference, $paymentReference, $paymentReference],
            $this->transferContentTemplate
        );

        // Bank transfer content must be safe for providers that reject punctuation
        // and other special characters. Keep only ASCII letters, digits and spaces.
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $content);
        $content = $ascii === false ? $content : $ascii;
        $content = preg_replace('/[^A-Za-z0-9 ]+/', ' ', $content) ?? '';
        $content = preg_replace('/\\s+/', ' ', trim($content)) ?? '';

        if ($content === '') {
            throw new \DomainException('Bank transfer content must contain at least one alphanumeric character.');
        }

        return strtoupper($content);
    }
}
