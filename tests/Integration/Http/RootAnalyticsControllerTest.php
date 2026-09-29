<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Audit\AuditLog;
use App\Domain\User\Enum\SessionStatus;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use App\Domain\User\UserSession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RootAnalyticsControllerTest extends WebTestCase
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
        $tool = new SchemaTool($this->entityManager);
        $tool->dropDatabase();
        if ($metadata !== []) {
            $tool->createSchema($metadata);
        }
    }

    public function testRegularUserCannotDiscoverRootAnalytics(): void
    {
        $user = $this->createUser('analytics-user', UserRole::USER);
        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/root/analytics');
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testAdminCannotDiscoverRootAnalytics(): void
    {
        $admin = $this->createUser('analytics-admin', UserRole::ADMIN);
        $client = static::createClient();
        $client->loginUser($admin);
        $client->request('GET', '/root/analytics');
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testRootSeesPresenceAndDoesNotSeeRoot(): void
    {
        $root = $this->createUser('analytics-root', UserRole::ROOT);
        $user = $this->createUser('analytics-target', UserRole::USER);
        $session = new UserSession('analytics-target-session', new \DateTimeImmutable('-30 seconds'));
        $session->assignUser($user);
        $session->recordRequest('GET', '/app/profile', '127.0.0.1', new \DateTimeImmutable('-30 seconds'));
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->loginUser($root);
        $client->request('GET', '/root/analytics');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $user->getUsername());
        self::assertSelectorTextContains('body', 'Online');
        self::assertSelectorTextNotContains('body', $root->getUsername());
    }

    public function testActionsAreFilteredAndPaginatedOnServer(): void
    {
        $root = $this->createUser('analytics-root', UserRole::ROOT);
        $user = $this->createUser('analytics-target', UserRole::USER);
        for ($i = 0; $i < 25; ++$i) {
            $this->entityManager->persist(new AuditLog(
                action: $i % 2 === 0 ? 'TEST_FILTERED_ACTION' : 'OTHER_ACTION',
                user: $user,
                entityType: 'User',
                entityId: (string) $user->getId(),
                ipAddress: '127.0.0.1',
                userAgent: 'analytics-test',
            ));
        }
        $this->entityManager->flush();

        $client = static::createClient();
        $client->loginUser($root);
        $client->request('GET', '/root/analytics?action=TEST_FILTERED_ACTION&page=2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Page 2');
        self::assertSelectorTextContains('body', 'TEST_FILTERED_ACTION');
        self::assertSelectorTextNotContains('body', 'OTHER_ACTION');
    }

    private function createUser(string $username, UserRole $role): User
    {
        $user = new User($username);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'correct-password'));
        $user->setRoles([$role->value]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
}
