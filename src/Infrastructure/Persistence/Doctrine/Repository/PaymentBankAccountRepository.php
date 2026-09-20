<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Repository;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
final class PaymentBankAccountRepository implements PaymentBankAccountRepositoryInterface {
 public function __construct(private readonly EntityManagerInterface $em){}
 public function save(PaymentBankAccount $account):void{$this->em->persist($account);$this->em->flush();}
 public function findById(int $id):?PaymentBankAccount{return $this->em->find(PaymentBankAccount::class,$id);}
 public function findAll():array{return $this->em->createQueryBuilder()->select('a')->from(PaymentBankAccount::class,'a')->orderBy('a.isActive','DESC')->addOrderBy('a.id','ASC')->getQuery()->getResult();}
 public function findActive():array{return $this->em->createQueryBuilder()->select('a')->from(PaymentBankAccount::class,'a')->where('a.isActive = true')->orderBy('a.id','ASC')->getQuery()->getResult();}
}
