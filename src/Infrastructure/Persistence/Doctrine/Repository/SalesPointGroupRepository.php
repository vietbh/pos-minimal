<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine\Repository;
use App\Domain\SalesPoint\SalesPointGroup;
use App\Domain\SalesPoint\Repository\SalesPointGroupRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
final class SalesPointGroupRepository implements SalesPointGroupRepositoryInterface { public function __construct(private readonly EntityManagerInterface $em){} public function save(SalesPointGroup $group):void{$this->em->persist($group);$this->em->flush();} public function findById(int $id):?SalesPointGroup{return $this->em->find(SalesPointGroup::class,$id);} public function findAll():array{return $this->em->getRepository(SalesPointGroup::class)->findBy([],['sortOrder'=>'ASC','id'=>'ASC']);} public function existsByCode(string $code, ?int $excludeId = null): bool { $qb=$this->em->getRepository(SalesPointGroup::class)->createQueryBuilder('g')->select('COUNT(g.id)')->andWhere('g.code = :code')->setParameter('code',trim($code)); if($excludeId!==null){$qb->andWhere('g.id != :id')->setParameter('id',$excludeId);} return (int)$qb->getQuery()->getSingleScalarResult()>0; } }
