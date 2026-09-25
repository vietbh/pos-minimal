<?php
declare(strict_types=1);
namespace App\Domain\SalesPoint\Repository;
use App\Domain\SalesPoint\SalesPointGroup;
interface SalesPointGroupRepositoryInterface { public function save(SalesPointGroup $group):void; public function findById(int $id):?SalesPointGroup; /** @return list<SalesPointGroup> */ public function findAll():array; }
