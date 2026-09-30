<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SettingsControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }

        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        parent::tearDown();
    }

    public function testAnonymousSettingsRedirectsToLogin(): void
    {
        $this->client->request('GET', '/app/settings');

        self::assertResponseRedirects('/auth/login');
    }

    public function testAuthenticatedSettingsShowsDefaultsAndProfileNavigation(): void
    {
        $this->loginAs('cashier');

        $this->client->request('GET', '/app/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/app/settings"]');
        self::assertSelectorExists('input[name="font_size"][value="medium"][checked]');
        self::assertSelectorExists('input[name="appearance"][value="system"][checked]');
        self::assertSelectorExists('input[name="density"][value="comfortable"][checked]');
        self::assertSelectorExists('input[name="high_contrast"]');
        self::assertSelectorExists('input[name="reduce_motion"]');
        self::assertSelectorExists('input[name="payment_sound_enabled"][checked]');
        self::assertSelectorExists('input[name="usage_guide_enabled"][checked]');
        self::assertSelectorExists('input[name="dashboard_default_tab"][value="auto"][checked]');
        self::assertSelectorExists('a[href="/app/guide"]');
        self::assertSelectorExists('a[href="/app/profile"]');
    }

    public function testHomeContainsProfileAndSettingsButtons(): void
    {
        $this->loginAs('cashier');

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/app/profile"]');
        self::assertSelectorTextContains('a[href="/app/profile"]', 'Hồ sơ');
        self::assertSelectorExists('a[href="/app/settings"]');
        self::assertSelectorTextContains('a[href="/app/settings"]', 'Cài đặt');
    }

    public function testHomeShowsPermissionAwareQuickActions(): void
    {
        $this->loginAs('cashier');

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1#home-title', 'Xin chào, cashier');
        self::assertSelectorExists('a[href="/app/profile"]');
        self::assertSelectorExists('a[href="/app/settings"]');
        self::assertSelectorExists('a[href="/app/pos"]');
        self::assertSelectorExists('a[href="/app/orders"]');
        self::assertSelectorExists('a[href="/app/customers"]');
        self::assertSelectorExists('a[href="/admin/products"]');
        self::assertSelectorExists('a[href="/admin/stock"]');
        self::assertSelectorExists('a[href="/app/debts"]');
        self::assertSelectorNotExists('a[href="/app/statistics"]');
    }

    public function testProfileContainsSettingsButton(): void
    {
        $this->loginAs('cashier');

        $this->client->request('GET', '/app/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/app/settings"]');
        self::assertSelectorTextContains('a[href="/app/settings"]', 'Cài đặt');
    }

    public function testPreferencesArePersistedAcrossReload(): void
    {
        $user = $this->loginAs('cashier');

        $this->client->request('GET', '/app/settings');
        $this->client->submitForm('Lưu cài đặt', [
            'font_size' => 'large',
            'appearance' => 'dark',
            'density' => 'compact',
            'high_contrast' => '1',
            'reduce_motion' => '1',
            'payment_sound_enabled' => '1',
            'usage_guide_enabled' => '1',
            'dashboard_default_tab' => 'operations',
        ]);

        self::assertResponseRedirects('/app/settings');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[data-font-size="large"]');
        self::assertSelectorExists('html[data-appearance="dark"]');
        self::assertSelectorExists('html[data-density="compact"]');
        self::assertSelectorExists('html[data-high-contrast="true"]');
        self::assertSelectorExists('html[data-reduce-motion="true"]');
        self::assertSelectorExists('input[name="font_size"][value="large"][checked]');
        self::assertSelectorExists('input[name="appearance"][value="dark"][checked]');
        self::assertSelectorExists('input[name="density"][value="compact"][checked]');
        self::assertSelectorExists('input[name="high_contrast"][checked]');
        self::assertSelectorExists('input[name="reduce_motion"][checked]');
        self::assertSelectorExists('input[name="payment_sound_enabled"][checked]');
        self::assertSelectorExists('input[name="usage_guide_enabled"][checked]');

        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $persisted);
        self::assertSame('large', $persisted->getFontSize());
        self::assertSame('dark', $persisted->getAppearance());
        self::assertSame('compact', $persisted->getUiDensity());
        self::assertTrue($persisted->hasHighContrast());
        self::assertTrue($persisted->hasReduceMotion());
        self::assertTrue($persisted->hasPaymentSoundEnabled());
        self::assertTrue($persisted->hasUsageGuideEnabled());
        self::assertSame('operations', $persisted->getDashboardDefaultTab());
    }


    public function testDashboardDefaultTabIsPermissionAwareAndPersists(): void
    {
        $user = $this->loginAs('admin-dashboard');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->flush();

        $this->client->request('GET', '/app/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="dashboard_default_tab"][value="auto"][checked]');
        self::assertSelectorExists('input[name="dashboard_default_tab"][value="administration"]');
        self::assertSelectorExists('input[name="dashboard_default_tab"][value="insights"]');

        $this->client->submitForm('Lưu cài đặt', [
            'font_size' => 'medium',
            'appearance' => 'system',
            'density' => 'comfortable',
            'dashboard_default_tab' => 'administration',
        ]);

        self::assertResponseRedirects('/app/settings');
        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $persisted);
        self::assertSame('administration', $persisted->getDashboardDefaultTab());

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.dashboard-tab.is-active[aria-current="page"]');
        self::assertSelectorTextContains('.dashboard-tab.is-active', 'Quản trị');
    }

    public function testUnauthorizedDashboardDefaultTabIsNotPersisted(): void
    {
        $user = $this->loginAs('cashier-unauthorized-tab');

        $this->client->request('GET', '/app/settings');
        $this->client->submitForm('Lưu cài đặt', [
            'font_size' => 'medium',
            'appearance' => 'system',
            'density' => 'comfortable',
            'dashboard_default_tab' => 'administration',
        ]);

        self::assertResponseRedirects('/app/settings');
        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $persisted);
        self::assertSame('auto', $persisted->getDashboardDefaultTab());
    }

    public function testPaymentSoundAndUsageGuideCanBeDisabled(): void
    {
        $user = $this->loginAs('cashier');

        $this->client->request('GET', '/app/settings');
        $this->client->submitForm('Lưu cài đặt', [
            'font_size' => 'medium',
            'appearance' => 'system',
            'density' => 'comfortable',
        ]);

        self::assertResponseRedirects('/app/settings');
        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $persisted);
        self::assertFalse($persisted->hasPaymentSoundEnabled());
        self::assertFalse($persisted->hasUsageGuideEnabled());
    }

    public function testUsageGuideIsProtectedAndRenders(): void
    {
        $this->loginAs('cashier');

        $this->client->request('GET', '/app/guide');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1#usage-guide-title');
        self::assertSelectorTextContains('h1#usage-guide-title', 'Hướng dẫn');
    }

    public function testPosUsesPersistedPaymentSoundPreferenceAndHasNoSpeakerToggle(): void
    {
        $user = $this->loginAs('cashier');
        $user->setPaymentSoundEnabled(true);
        $this->entityManager->flush();

        $this->client->request('GET', '/app/pos');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pos-page[data-pos-checkout-payment-sound-enabled-value="true"]');
        self::assertSelectorNotExists('.pos-payment-speaker');
    }

    public function testInvalidPreferenceIsRejectedAndNotPersisted(): void
    {
        $user = $this->loginAs('cashier');

        $this->client->request('GET', '/app/settings');
        $this->client->submitForm('Lưu cài đặt', [
            'font_size' => 'invalid',
            'appearance' => 'dark',
            'density' => 'compact',
        ]);

        self::assertResponseRedirects('/app/settings');

        $this->entityManager->clear();
        $persisted = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $persisted);
        self::assertSame('medium', $persisted->getFontSize());
        self::assertSame('system', $persisted->getAppearance());
        self::assertSame('comfortable', $persisted->getUiDensity());
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $this->loginAs('cashier');

        $this->client->request('POST', '/app/settings', [
            'font_size' => 'large',
            'appearance' => 'dark',
            'density' => 'compact',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function loginAs(string $username): User
    {
        $user = new User($username);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'correct-password'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('GET', '/auth/login');
        $this->client->submitForm('Đăng nhập', [
            '_username' => $username,
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/');

        return $user;
    }
}
