<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\ListDebts;
final readonly class ListDebtsInput { public function __construct(public string $search='', public ?string $status=null, public int $page=1, public int $perPage=20) {} }
