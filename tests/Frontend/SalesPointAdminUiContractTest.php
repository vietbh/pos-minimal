<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SalesPointAdminUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSalesPointAdminHasGroupManagementAndToggleControls(): void
    {
        $html = file_get_contents($this->root.'/templates/admin/sales_point/index.html.twig');
        $controller = file_get_contents($this->root.'/src/Controller/Admin/SalesPointController.php');

        self::assertStringContainsString("path('admin_sales_point_create')", $html);
        self::assertStringContainsString("path('admin_sales_point_group_create')", $html);
        self::assertStringContainsString("path('admin_sales_point_group_toggle'", $html);
        self::assertStringContainsString("path('admin_sales_point_toggle'", $html);
        self::assertStringContainsString('sales_point.group_list', $html);
        self::assertStringContainsString("Route('/groups/create'", $controller);
        self::assertStringContainsString("Route('/groups/{id<\\d+>}/toggle'", $controller);
        self::assertStringContainsString('group->update(', $controller);
    }

    public function testSalesPointAndGroupRepositoriesSupportCodeUniqueness(): void
    {
        $pointRepo = file_get_contents($this->root.'/src/Infrastructure/Persistence/Doctrine/Repository/SalesPointRepository.php');
        $groupRepo = file_get_contents($this->root.'/src/Infrastructure/Persistence/Doctrine/Repository/SalesPointGroupRepository.php');

        self::assertStringContainsString('existsByCode(string $code, ?int $excludeId = null)', $pointRepo);
        self::assertStringContainsString('existsByCode(string $code, ?int $excludeId = null)', $groupRepo);
    }
}
