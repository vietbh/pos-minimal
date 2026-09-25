<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FinalMvpRegressionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testProductionConfigurationUsesSafeRuntimeSecretTemplate(): void
    {
        $env = file_get_contents($this->root.'/.env.prod');
        self::assertIsString($env);
        self::assertStringContainsString('APP_ENV=prod', $env);
        self::assertStringContainsString('APP_SECRET=CHANGE_ME_IN_RUNTIME_ENV', $env);
        self::assertStringContainsString('DATABASE_URL="mysql://CHANGE_ME:CHANGE_ME@', $env);
        self::assertStringNotContainsString('BANK_NOTIFICATION_WEBHOOK_TOKEN=', $env);
        self::assertStringNotContainsString('dev_mobile_pos:dev_mobile_pos@', $env);
    }

    public function testProductionReadinessChecksRuntimeConfiguration(): void
    {
        $source = file_get_contents($this->root.'/src/Command/ProductionReadinessCheckCommand.php');
        self::assertIsString($source);
        self::assertStringContainsString('CHANGE_ME_IN_RUNTIME_ENV', $source);
        self::assertStringContainsString('DATABASE_URL still contains a placeholder', $source);
        self::assertStringNotContainsString('BANK_NOTIFICATION_WEBHOOK_TOKEN', $source);
    }

    public function testMessengerAndProductionCacheConfigurationRemainBounded(): void
    {
        $messenger = file_get_contents($this->root.'/config/packages/messenger.yaml');
        $doctrine = file_get_contents($this->root.'/config/packages/doctrine.yaml');
        self::assertIsString($messenger);
        self::assertIsString($doctrine);
        self::assertStringContainsString('max_retries: 3', $messenger);
        self::assertStringContainsString('max_delay: 30000', $messenger);
        self::assertStringContainsString('doctrine.result_cache_pool', $doctrine);
        self::assertStringContainsString('doctrine.system_cache_pool', $doctrine);
    }

    public function testBusinessMutationsAreNotRoutedToMessenger(): void
    {
        $messenger = file_get_contents($this->root.'/config/packages/messenger.yaml');
        self::assertIsString($messenger);
        foreach ([
            'App\\Application\\Order\\Command\\Checkout',
            'App\\Application\\Debt\\Command\\PayDebt',
            'App\\Application\\Product\\Command\\AdjustStock',
            'App\\Application\\Order\\Command\\CancelOrder',
        ] as $businessMutation) {
            self::assertStringNotContainsString($businessMutation.': async', $messenger);
        }
        self::assertStringContainsString('App\\Application\\Product\\Message\\ProcessProductImage: async', $messenger);
    }

    public function testProductionRunbookAndCronTemplateExist(): void
    {
        self::assertFileExists($this->root.'/FINAL-PRODUCTION-RUNBOOK.md');
        self::assertFileExists($this->root.'/deploy/cron/mobile-pos-maintenance.cron');
        self::assertFileExists($this->root.'/PHASE-11-19-FINAL-IMPLEMENTATION.md');
    }

    public function testPosRemoveControlIsIconOnlyAndTouchFriendly(): void
    {
        $source = file_get_contents($this->root.'/assets/controllers/pos_checkout_controller.js');
        $css = file_get_contents($this->root.'/assets/styles/app.css');
        self::assertIsString($source);
        self::assertIsString($css);
        self::assertStringContainsString("remove.textContent = '×';", $source);
        self::assertStringContainsString('.pos-remove-button{margin-left:auto;width:48px;min-width:48px;min-height:48px;', $css);
        self::assertStringNotContainsString("remove.textContent = this.cartRemoveLabelValue", $source);
    }

    public function testSourceControllersRemainInSourceTree(): void
    {
        self::assertFileExists($this->root.'/assets/controllers/pos_checkout_controller.js');
    }
}
