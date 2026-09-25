<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SalesPointAndPasswordContractTest extends TestCase
{
    public function testSalesPointAndPasswordContractsExist(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'src/Domain/SalesPoint/SalesPoint.php',
            'src/Domain/SalesPoint/SalesPointGroup.php',
            'src/Domain/SalesPoint/Enum/SalesPointType.php',
            'src/Domain/SalesPoint/Enum/SalesPointStatus.php',
            'src/Application/SalesPoint/SalesPointService.php',
            'src/Application/User/ChangePasswordService.php',
            'src/Controller/Admin/SalesPointController.php',
            'src/Controller/Application/SalesPointContextController.php',
            'templates/admin/sales_point/index.html.twig',
            'migrations/Version20260924121500.php',
        ];
        foreach ($required as $file) self::assertFileExists($root.'/'.$file);
        self::assertFileDoesNotExist($root.'/public/assets/controllers/sales_point_controller.js');
    }
}
