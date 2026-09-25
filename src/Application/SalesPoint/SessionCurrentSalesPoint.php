<?php
declare(strict_types=1);
namespace App\Application\SalesPoint;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
final class SessionCurrentSalesPoint implements CurrentSalesPoint {
 private const KEY='_pos_current_sales_point_id';
 public function __construct(private readonly RequestStack $requestStack,private readonly SalesPointRepositoryInterface $repository){}
 public function get():?SalesPoint{$request=$this->requestStack->getCurrentRequest();if($request===null)return null;$id=$request->getSession()->get(self::KEY);if(!is_int($id)&&!ctype_digit((string)$id))return null;$point=$this->repository->findById((int)$id);if($point===null||!$point->isActive()){$this->clear();return null;}return $point;}
 public function set(SalesPoint $salesPoint):void{if(!$salesPoint->isActive())throw new \DomainException('Only active sales points can be selected.');$request=$this->requestStack->getCurrentRequest();if($request===null)throw new \LogicException('Current sales point requires an HTTP request.');$request->getSession()->set(self::KEY,$salesPoint->getId());}
 public function clear():void{$request=$this->requestStack->getCurrentRequest();if($request!==null)$request->getSession()->remove(self::KEY);}
}
