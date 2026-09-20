<?php
declare(strict_types=1);
namespace App\Domain\Payment;
use App\Domain\Order\Order;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'external_payment_transactions')]
#[ORM\UniqueConstraint(name: 'uniq_external_payment_provider_tx', columns: ['provider','external_transaction_id'])]
#[ORM\Index(name: 'idx_external_payment_status', columns: ['status'])]
#[ORM\Index(
    name: 'idx_external_payment_account',
    columns: ['payment_bank_account_id']
)]
#[ORM\Index(
    name: 'idx_external_payment_order',
    columns: ['matched_order_id']
)]
#[ORM\Index(
    name: 'IDX_EXTERNAL_PAYMENT_PAYMENT',
    columns: ['matched_payment_id']
)]
class ExternalPaymentTransaction
{
    #[ORM\Id, ORM\GeneratedValue]
    #[ORM\Column(type:'bigint', options:['unsigned'=>true])]
    private ?int $id=null;
    #[ORM\Column(length:30)] private string $provider;
    #[ORM\Column(name:'external_transaction_id',length:120)] private string $externalTransactionId;
    #[ORM\ManyToOne(targetEntity:PaymentBankAccount::class)]
    #[ORM\JoinColumn(name:'payment_bank_account_id',referencedColumnName:'id',nullable:false,onDelete:'RESTRICT')]
    private PaymentBankAccount $paymentBankAccount;
    #[ORM\Column(type:'money')] private \App\Domain\Shared\ValueObject\Money $amount;
    #[ORM\Column(length:500)] private string $description;
    #[ORM\Column(name:'transaction_reference',length:120,nullable:true)] private ?string $transactionReference;
    #[ORM\Column(name:'occurred_at',type:'datetime_immutable')] private \DateTimeImmutable $occurredAt;
    #[ORM\Column(length:30)] private string $status;
    #[ORM\ManyToOne(targetEntity:Order::class)]
    #[ORM\JoinColumn(name:'matched_order_id',referencedColumnName:'id',nullable:true,onDelete:'SET NULL')]
    private ?Order $matchedOrder=null;
    #[ORM\ManyToOne(targetEntity:\App\Domain\Order\Payment::class)]
    #[ORM\JoinColumn(name:'matched_payment_id',referencedColumnName:'id',nullable:true,onDelete:'SET NULL')]
    private ?\App\Domain\Order\Payment $matchedPayment=null;
    #[ORM\Column(name:'matched_at',type:'datetime_immutable',nullable:true)] private ?\DateTimeImmutable $matchedAt=null;
    #[ORM\Column(name:'created_at',type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name:'updated_at',type:'datetime_immutable')] private \DateTimeImmutable $updatedAt;

    public function __construct(string $provider,string $externalTransactionId,PaymentBankAccount $account,\App\Domain\Shared\ValueObject\Money $amount,string $description,\DateTimeImmutable $occurredAt,?string $transactionReference=null,string $status='UNMATCHED') {
        $this->provider=$provider; $this->externalTransactionId=trim($externalTransactionId); $this->paymentBankAccount=$account; $this->amount=$amount;
        $this->description=trim($description); $this->occurredAt=$occurredAt; $this->transactionReference=$transactionReference !== null ? trim($transactionReference) ?: null : null;
        $this->status=$status; $this->createdAt=new \DateTimeImmutable(); $this->updatedAt=$this->createdAt;
    }
    public function getId():?int{return $this->id;} public function getExternalTransactionId():string{return $this->externalTransactionId;}
    public function getPaymentBankAccount():PaymentBankAccount{return $this->paymentBankAccount;} public function getAmount():\App\Domain\Shared\ValueObject\Money{return $this->amount;}
    public function getDescription():string{return $this->description;} public function getTransactionReference():?string{return $this->transactionReference;}
    public function getProvider():string{return $this->provider;} public function getStatus():string{return $this->status;} public function getMatchedOrder():?Order{return $this->matchedOrder;} public function getMatchedPayment():?\App\Domain\Order\Payment{return $this->matchedPayment;} public function getOccurredAt():\DateTimeImmutable{return $this->occurredAt;}
    public function matchOrder(Order $order,string $status='MATCHED'):void{$this->matchedOrder=$order;$this->status=$status;$this->matchedAt=new \DateTimeImmutable();$this->updatedAt=new \DateTimeImmutable();} public function matchPayment(\App\Domain\Order\Payment $payment,string $status='MATCHED'):void{$this->matchedPayment=$payment;$this->matchedOrder=$payment->getOrder();$this->status=$status;$this->matchedAt=new \DateTimeImmutable();$this->updatedAt=new \DateTimeImmutable();}
    public function setStatus(string $status):void{$this->status=$status;$this->updatedAt=new \DateTimeImmutable();}
}
