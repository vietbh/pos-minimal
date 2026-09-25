<?php
declare(strict_types=1);
namespace App\Application\SalesPoint;
use App\Domain\SalesPoint\SalesPoint;
interface CurrentSalesPoint { public function get():?SalesPoint; public function set(SalesPoint $salesPoint):void; public function clear():void; }
