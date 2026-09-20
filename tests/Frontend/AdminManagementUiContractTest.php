<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AdminManagementUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testAdminManagementTemplatesUseMobileFirstWorkspacePrimitives(): void
    {
        foreach ([
            'templates/admin/product/index.html.twig',
            'templates/admin/product/form.html.twig',
            'templates/admin/product/show.html.twig',
            'templates/admin/product_category/index.html.twig',
            'templates/admin/product_category/form.html.twig',
            'templates/admin/stock/index.html.twig',
            'templates/admin/stock/show.html.twig',
            'templates/admin/payment/accounts.html.twig',
        ] as $file) {
            $html = (string) file_get_contents($this->root . '/' . $file);
            self::assertStringContainsString('admin-workspace', $html, $file);
            self::assertStringContainsString('min-height', file_get_contents($this->root . '/assets/styles/phase-uiux-7.css'));
        }
    }

    public function testCriticalAdminActionsRemainPermissionGated(): void
    {
        $templates = [
            'templates/admin/product/index.html.twig',
            'templates/admin/product/form.html.twig',
            'templates/admin/product/show.html.twig',
            'templates/admin/product_category/index.html.twig',
            'templates/admin/stock/show.html.twig',
        ];

        foreach ($templates as $file) {
            $html = (string) file_get_contents($this->root . '/' . $file);
            if (str_contains($html, 'New product') || str_contains($html, 'Edit') || str_contains($html, 'Adjust stock')) {
                self::assertTrue(str_contains($html, 'is_granted(') || str_contains($file, 'form.html.twig'), $file);
            }
        }
    }

    public function testStatisticsKeepsServerSideFilteringAndPermissionBoundary(): void
    {
        $html = (string) file_get_contents($this->root . '/templates/statistics/index.html.twig');
        $controller = (string) file_get_contents($this->root . '/src/Controller/Statistics/StatisticsController.php');
        self::assertStringContainsString('method="get"', $html);
        self::assertStringContainsString("Permission::STATISTICS_VIEW", $controller);
        self::assertStringContainsString('statistics-filter', $html);
    }
}
