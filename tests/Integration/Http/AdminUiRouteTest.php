<?php

declare(strict_types=1);
namespace App\Tests\Integration\Http;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class AdminUiRouteTest extends WebTestCase
{
    public function testPhase29RoutesAreRegistered(): void
    {
        $routes=static::getContainer()->get('router')->getRouteCollection();
        foreach(['admin_products_index','admin_products_new','admin_products_show','admin_products_edit','admin_products_price','admin_products_activate','admin_products_deactivate','admin_products_stock','customers_index','customers_new','customers_show','customers_edit','admin_stock_index','admin_stock_show','admin_stock_adjust'] as $name) self::assertNotNull($routes->get($name),$name);
    }
}
