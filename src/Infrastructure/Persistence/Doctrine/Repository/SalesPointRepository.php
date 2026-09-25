<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Repository;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
final class SalesPointRepository implements SalesPointRepositoryInterface { public function __construct(private readonly EntityManagerInterface $em){} public function save(SalesPoint $salesPoint):void{$this->em->persist($salesPoint);$this->em->flush();} public function findById(int $id):?SalesPoint{return $this->em->find(SalesPoint::class,$id);} public function findAll():array{return $this->em->getRepository(SalesPoint::class)->findBy([],['id'=>'ASC']);} public function findActive():array{return $this->em->getRepository(SalesPoint::class)->findBy(['status'=>'ACTIVE'],['id'=>'ASC']);} public function existsByCode(string $code, ?int $excludeId = null): bool { $qb=$this->em->getRepository(SalesPoint::class)->createQueryBuilder('sp')->select('COUNT(sp.id)')->andWhere('sp.code = :code')->setParameter('code',trim($code)); if($excludeId!==null){$qb->andWhere('sp.id != :id')->setParameter('id',$excludeId);} return (int)$qb->getQuery()->getSingleScalarResult()>0; } }
