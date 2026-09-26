<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ProfileRoleUiContractTest extends TestCase
{
    public function testProfileContainsRoleManagementAndModernTealDarkCardContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = file_get_contents($root . '/templates/application/profile.html.twig');
        $css = file_get_contents($root . '/assets/styles/app.css');

        self::assertIsString($profile);
        self::assertIsString($css);
        self::assertStringContainsString("csrf_token('user_role_management')", $profile);
        self::assertStringContainsString('name="role_target_id"', $profile);
        self::assertStringContainsString('name="role"', $profile);
        self::assertStringContainsString('ROLE_ADMIN', $profile);
        self::assertStringContainsString('font-size-option', $css);
        self::assertStringContainsString('html[data-appearance="dark"] .font-size-option', $css);
    }
}
