<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AdminStatisticsSecurityUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testStatisticsUiIsSemanticResponsiveAndServerAuthoritative(): void
    {
        $html = (string) file_get_contents($this->root . '/templates/statistics/index.html.twig');
        $controller = (string) file_get_contents($this->root . '/assets/controllers/statistics_filter_controller.js');

        self::assertStringContainsString('data-controller="statistics-filter"', $html);
        self::assertStringContainsString('method="get"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('<caption class="sr-only">', $html);
        self::assertStringContainsString('scope="col"', $html);
        self::assertStringContainsString('statistics-filter-dates', $html);
        self::assertStringContainsString('statistics-kpi-grid', $html);
        self::assertStringContainsString('statistics-chart-grid', $html);
        self::assertStringContainsString('data-statistics-filter-target="financialChart"', $html);
        self::assertStringContainsString('data-statistics-filter-target="paymentChart"', $html);
        self::assertStringContainsString('apexcharts', (string) file_get_contents($this->root . '/importmap.php'));
        self::assertStringContainsString("import ApexCharts from 'apexcharts';", $controller);
        self::assertStringContainsString('new ApexCharts', $controller);
        self::assertStringContainsString('|money_vnd', $html);
        self::assertStringContainsString('pos.payment.', $html);
        self::assertStringContainsString('server remains authoritative', $controller);
        self::assertStringNotContainsString('new Date(', $controller);
    }

    public function testSecurityUiDoesNotReplaceBackendAuthorization(): void
    {
        $controller = (string) file_get_contents($this->root . '/src/Controller/Statistics/StatisticsController.php');
        $forbidden = (string) file_get_contents($this->root . '/templates/bundles/TwigBundle/Exception/error403.html.twig');

        self::assertStringContainsString('Permission::STATISTICS_VIEW', $controller);
        self::assertStringContainsString('security-denied', $forbidden);
        self::assertStringContainsString('access.denied_message', $forbidden);
    }

    public function testPhase10_7UsesOnlySourceStimulusControllerPath(): void
    {
        self::assertFileExists($this->root . '/assets/controllers/statistics_filter_controller.js');
        self::assertFileDoesNotExist($this->root . '/assets/controllers/public/assets/controllers/statistics_filter_controller.js');
    }

    public function testAdminUiKeepsPermissionGatedActions(): void
    {
        $product = (string) file_get_contents($this->root . '/templates/admin/product/index.html.twig');
        $stock = (string) file_get_contents($this->root . '/templates/admin/stock/show.html.twig');

        self::assertStringContainsString("is_granted('PRODUCT_EDIT')", $product);
        self::assertStringContainsString("is_granted('PRODUCT_EDIT')", $stock);
        self::assertStringContainsString("is_granted('STOCK_ADJUST')", $stock);
        self::assertStringContainsString('csrf_token(', $stock);
    }
}
