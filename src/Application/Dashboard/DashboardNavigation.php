<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

final readonly class DashboardNavigation
{
    /**
     * @param list<array{key:string,label:string}> $tabs
     */
    public function __construct(
        public array $tabs,
        public string $activeTab,
        public string $defaultTab,
    ) {
    }
}
