<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Repository;
use App\Domain\Payment\ExternalPaymentTransaction;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
final class ExternalPaymentTransactionRepository implements ExternalPaymentTransactionRepositoryInterface {
 public function __construct(private readonly EntityManagerInterface $em){}
 public function save(ExternalPaymentTransaction $transaction):void{$this->em->persist($transaction);}
 public function findByProviderTransactionId(string $provider,string $externalTransactionId):?ExternalPaymentTransaction{return $this->em->createQueryBuilder()->select('t')->from(ExternalPaymentTransaction::class,'t')->where('t.provider=:p')->andWhere('t.externalTransactionId=:id')->setParameter('p',$provider)->setParameter('id',$externalTransactionId)->setMaxResults(1)->getQuery()->getOneOrNullResult();}
 public function findLatestByOrderId(int $orderId): ?ExternalPaymentTransaction
 {
     return $this->em->createQueryBuilder()
         ->select('t')
         ->from(ExternalPaymentTransaction::class, 't')
         ->where('IDENTITY(t.matchedOrder) = :orderId')
         ->setParameter('orderId', $orderId)
         ->orderBy('t.occurredAt', 'DESC')
         ->addOrderBy('t.id', 'DESC')
         ->setMaxResults(1)
         ->getQuery()
         ->getOneOrNullResult();
 }
}
