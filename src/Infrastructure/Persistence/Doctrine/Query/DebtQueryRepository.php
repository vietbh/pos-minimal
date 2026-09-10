<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Query;
use App\Application\Debt\Query\DebtQueryRepositoryInterface;
use App\Application\Debt\Query\GetDebt\{DebtDetailResult,DebtPaymentResult};
use App\Application\Debt\Query\ListDebts\{DebtListItemResult,DebtListResult,ListDebtsInput};
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtPayment;
use Doctrine\ORM\EntityManagerInterface;
final class DebtQueryRepository implements DebtQueryRepositoryInterface {
 public function __construct(private readonly EntityManagerInterface $entityManager) {}
 public function listDebts(ListDebtsInput $input): DebtListResult {
  $page=max(1,$input->page); $perPage=min(50,max(1,$input->perPage));
  $qb=$this->entityManager->createQueryBuilder()->select('d.id AS id','IDENTITY(d.customer) AS customerId','c.name AS customerName','IDENTITY(d.order) AS orderId','o.orderNumber AS orderNumber','d.originalAmount AS originalAmount','d.status AS status','d.createdAt AS createdAt','COALESCE(SUM(dp.amount), 0) AS paidAmount')->from(Debt::class,'d')->join('d.customer','c')->join('d.order','o')->leftJoin(DebtPayment::class,'dp','WITH','dp.debt = d')->groupBy('d.id','c.id','c.name','o.id','o.orderNumber','d.originalAmount','d.status','d.createdAt')->orderBy('d.createdAt','DESC')->addOrderBy('d.id','DESC');
  $search=trim($input->search); if($search!=='')$qb->andWhere('LOWER(c.name) LIKE :search OR LOWER(o.orderNumber) LIKE :search')->setParameter('search','%'.mb_strtolower($search).'%');
  if($input->status!==null&&$input->status!=='')$qb->andWhere('d.status = :status')->setParameter('status',$input->status);
  $countQb=$this->entityManager->createQueryBuilder()->select('COUNT(d.id)')->from(Debt::class,'d')->join('d.customer','c')->join('d.order','o'); if($search!=='')$countQb->andWhere('LOWER(c.name) LIKE :search OR LOWER(o.orderNumber) LIKE :search')->setParameter('search','%'.mb_strtolower($search).'%'); if($input->status!==null&&$input->status!=='')$countQb->andWhere('d.status = :status')->setParameter('status',$input->status); $total=(int)$countQb->getQuery()->getSingleScalarResult();
  $rows=$qb->setFirstResult(($page-1)*$perPage)->setMaxResults($perPage)->getQuery()->getArrayResult(); $items=[]; foreach($rows as $r){$original=(string)$r['originalAmount'];$paid=$this->decimal((string)$r['paidAmount']);$remaining=$this->subtract($original,$paid);$items[]=new DebtListItemResult((int)$r['id'],(int)$r['customerId'],(string)$r['customerName'],(int)$r['orderId'],(string)$r['orderNumber'],$original,$paid,$remaining,is_object($r['status'])?$r['status']->value:(string)$r['status'],$this->date($r['createdAt']));} return new DebtListResult($items,$page,$perPage,$total);
 }
 public function findDebtById(int $id): ?DebtDetailResult {
  if($id<=0)throw new \InvalidArgumentException('Debt ID must be greater than zero.');
  $d=$this->entityManager->createQueryBuilder()->select('d','c','o')->from(Debt::class,'d')->join('d.customer','c')->join('d.order','o')->where('d.id=:id')->setParameter('id',$id)->getQuery()->getOneOrNullResult(); if(!$d instanceof Debt||$d->getId()===null)return null;
  $paid=$d->getPaidAmount()->toDecimal();$payments=[];foreach($d->getPayments() as $p){if($p->getId()===null)continue;$payments[]=new DebtPaymentResult($p->getId(),$p->getAmount()->toDecimal(),$p->getUser()->getUserIdentifier(),$p->getCreatedAt());}
  return new DebtDetailResult($d->getId(),$d->getCustomer()->getId()??0,$d->getCustomer()->getName(),$d->getCustomer()->getPhone(), $d->getOrder()->getId()??0,$d->getOrder()->getOrderNumber()->value(),$d->getOriginalAmount()->toDecimal(),$paid,$d->getRemainingAmount()->toDecimal(),$d->getStatus()->value,$d->getCreatedAt(),$payments);
 }
 private function decimal(string $v):string{return \App\Domain\Shared\ValueObject\Money::fromDecimal($v)->toDecimal();}
 private function subtract(string $a,string $b):string{return \App\Domain\Shared\ValueObject\Money::fromDecimal($a)->subtract(\App\Domain\Shared\ValueObject\Money::fromDecimal($b))->toDecimal();}
 private function date(mixed $v):\DateTimeImmutable{return $v instanceof \DateTimeImmutable?$v:\DateTimeImmutable::createFromMutable($v);}
}
