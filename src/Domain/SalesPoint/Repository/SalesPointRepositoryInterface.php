<?php
declare(strict_types=1);
namespace App\Domain\SalesPoint\Repository;
use App\Domain\SalesPoint\SalesPoint;
interface SalesPointRepositoryInterface { public function save(SalesPoint $salesPoint):void; public function findById(int $id):?SalesPoint; /** @return list<SalesPoint> */ public function findAll():array; /** @return list<SalesPoint> */ public function findActive():array; }
