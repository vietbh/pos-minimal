<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SwitchUserSecurityTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }
    }

    public function testAdminCanUseNativeSwitchUserForUserAccount(): void
    {
        $admin = $this->createUser('switch-admin', UserRole::ADMIN);
        $target = $this->createUser('switch-target', UserRole::USER);

        $client = static::createClient();
        $this->login($client, $admin->getUsername());

        $client->request('GET', '/?_switch_user='.$target->getUsername());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            $target->getUsername(),
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testAdminCannotSwitchToAnotherAdmin(): void
    {
        $admin = $this->createUser('switch-admin-admin-test', UserRole::ADMIN);
        $targetAdmin = $this->createUser('switch-target-admin-test', UserRole::ADMIN);

        $client = static::createClient();
        $this->login($client, $admin->getUsername());

        $client->request('GET', '/?_switch_user='.$targetAdmin->getUsername());

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAdminCannotSwitchToRoot(): void
    {
        $admin = $this->createUser('switch-admin-root-test', UserRole::ADMIN);
        $root = $this->createUser('switch-root-test', UserRole::ROOT);

        $client = static::createClient();
        $this->login($client, $admin->getUsername());

        $client->request('GET', '/?_switch_user='.$root->getUsername());

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testRootCanSwitchToAdmin(): void
    {
        $root = $this->createUser('switch-root', UserRole::ROOT);
        $admin = $this->createUser('switch-admin-target', UserRole::ADMIN);

        $client = static::createClient();
        $this->login($client, $root->getUsername());

        $client->request('GET', '/?_switch_user='.$admin->getUsername());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            $admin->getUsername(),
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testNativeSwitchUserExitRestoresOriginalAccountInsteadOfLoggingOut(): void
    {
        $root = $this->createUser('switch-exit-root', UserRole::ROOT);
        $target = $this->createUser('switch-exit-target', UserRole::USER);

        $client = static::createClient();
        $this->login($client, $root->getUsername());

        $client->request('GET', '/?_switch_user='.$target->getUsername());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($target->getUsername(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/app/switch-user/exit?_switch_user=_exit');
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($root->getUsername(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($target->getUsername(), (string) $client->getResponse()->getContent());
    }

    public function testInactiveImpersonatedUserCausesExitWithoutKillingOriginalAdminSession(): void
    {
        $admin = $this->createUser('switch-live-admin', UserRole::ADMIN);
        $target = $this->createUser('switch-deactivate-target', UserRole::USER);

        $client = static::createClient();
        $this->login($client, $admin->getUsername());

        $client->request('GET', '/?_switch_user='.$target->getUsername());
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $target = $this->entityManager->find(User::class, $target->getId());
        self::assertInstanceOf(User::class, $target);
        $target->deactivate();
        $this->entityManager->flush();

        $client->request('GET', '/');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            $admin->getUsername(),
            (string) $client->getResponse()->getContent(),
        );
    }

    private function login(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $username,
    ): void {
        $client->request('GET', '/auth/login');
        $client->submitForm('Sign in', [
            '_username' => $username,
            '_password' => 'correct-password',
        ]);
        self::assertResponseRedirects('/');
    }

    private function createUser(string $username, UserRole $role): User
    {
        $user = new User($username);
        $user->setPasswordHash(
            $this->passwordHasher->hashPassword($user, 'correct-password'),
        );
        $user->setRoles([$role->value]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
