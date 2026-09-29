<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Audit\AuditLog;
use App\Domain\Order\Order;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerTest extends WebTestCase
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
        $tool = new SchemaTool($this->entityManager);
        $tool->dropDatabase();
        if ($metadata !== []) {
            $tool->createSchema($metadata);
        }
        $this->client->disableReboot();
    }

    public function testAnonymousCannotOpenUserManagement(): void
    {
        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('/auth/login');
    }

    public function testRegularUserIsForbidden(): void
    {
        $user = $this->createUser('cashier', UserRole::USER);
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanListCreateEditDeactivateReactivateAndResetPassword(): void
    {
        $admin = $this->createUser('admin', UserRole::ADMIN);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Users');
        self::assertSelectorExists('a[href="/admin/users/new"]');

        $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="username"]');
        self::assertSelectorExists('input[name="password"]');

        $this->client->submitForm('Create user', [
            'username' => 'cashier-new',
            'role' => UserRole::USER->value,
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);
        self::assertResponseRedirects('/admin/users');

        $created = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'cashier-new']);
        self::assertInstanceOf(User::class, $created);
        self::assertTrue($created->hasPassword());
        self::assertNotSame('correct-password', $created->getPassword());

        $id = $created->getId();
        self::assertNotNull($id);

        $this->client->request('GET', '/admin/users/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Save changes', [
            'username' => 'cashier-renamed',
            'role' => UserRole::ADMIN->value,
        ]);
        self::assertResponseRedirects('/admin/users/'.$id);

        $this->entityManager->clear();
        $created = $this->entityManager->find(User::class, $id);
        self::assertInstanceOf(User::class, $created);
        self::assertSame('cashier-renamed', $created->getUsername());
        self::assertTrue($created->hasRole(UserRole::USER));

        $this->client->request('GET', '/admin/users/'.$id);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Deactivate user', [
            '_token' => $this->tokenFromCurrentResponse('admin_user_state'),
            'active' => '0',
        ]);
        self::assertResponseRedirects('/admin/users/'.$id);

        $this->entityManager->clear();
        $created = $this->entityManager->find(User::class, $id);
        self::assertInstanceOf(User::class, $created);
        self::assertFalse($created->isActive());

        $this->client->request('GET', '/admin/users/'.$id);
        $this->client->submitForm('Activate user', [
            '_token' => $this->tokenFromCurrentResponse('admin_user_state'),
            'active' => '1',
        ]);
        self::assertResponseRedirects('/admin/users/'.$id);

        $this->client->request('GET', '/admin/users/'.$id);
        $this->client->submitForm('Reset password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        self::assertResponseRedirects('/admin/users/'.$id);

        $this->entityManager->clear();
        $created = $this->entityManager->find(User::class, $id);
        self::assertInstanceOf(User::class, $created);
        self::assertTrue($this->passwordHasher->isPasswordValid($created, 'new-password'));

        $audits = $this->entityManager->getRepository(AuditLog::class)->findBy(['entityType' => 'User', 'entityId' => (string) $id]);
        $actions = array_map(static fn (AuditLog $audit): string => $audit->getAction(), $audits);
        self::assertContains('USER_CREATED', $actions);
        self::assertContains('USER_UPDATED', $actions);
        self::assertContains('USER_DEACTIVATED', $actions);
        self::assertContains('USER_ACTIVATED', $actions);
        self::assertContains('USER_PASSWORD_RESET', $actions);
        foreach ($audits as $audit) {
            self::assertStringNotContainsString('new-password', json_encode($audit->getNewValues(), JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('correct-password', json_encode($audit->getNewValues(), JSON_THROW_ON_ERROR));
        }
    }

    public function testAdminCannotSeeAdminOrRootAndRootCanSeeAdminButRootRemainsHidden(): void
    {
        $admin = $this->createUser('admin-hierarchy', UserRole::ADMIN);
        $otherAdmin = $this->createUser('other-admin-hierarchy', UserRole::ADMIN);
        $user = $this->createUser('user-hierarchy', UserRole::USER);
        $root = $this->createUser('root-hierarchy', UserRole::ROOT);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $user->getUsername());
        self::assertSelectorTextContains('body', $otherAdmin->getUsername());
        self::assertSelectorTextNotContains('body', $root->getUsername());

        $this->client->request('GET', '/admin/users/'.$otherAdmin->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/admin/users/'.$otherAdmin->getId().'/edit"]');

        $this->client->request('GET', '/admin/users/'.$root->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->loginUser($root);
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $otherAdmin->getUsername());
        self::assertSelectorTextContains('body', $user->getUsername());
        self::assertSelectorTextNotContains('body', $root->getUsername());

        $this->client->request('GET', '/admin/users/'.$root->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminCannotCreateAdminButRootCan(): void
    {
        $admin = $this->createUser('admin-create-hierarchy', UserRole::ADMIN);
        $root = $this->createUser('root-create-hierarchy', UserRole::ROOT);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="role"] option[value="ROLE_ADMIN"]');

        $this->client->submitForm('Create user', [
            'username' => 'forbidden-admin',
            'role' => UserRole::ADMIN->value,
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->client->loginUser($root);
        $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="role"] option[value="ROLE_ADMIN"]');
    }

    public function testDeactivationPreservesHistoricalActorReferences(): void
    {
        $admin = $this->createUser('admin-history', UserRole::ADMIN);
        $target = $this->createUser('historical-user', UserRole::USER);
        $order = new Order(new OrderNumber('USER-HISTORY-'.bin2hex(random_bytes(4))), $target);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $orderId = $order->getId();
        $targetId = $target->getId();
        self::assertNotNull($orderId);
        self::assertNotNull($targetId);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users/'.$targetId);
        $this->client->submitForm('Deactivate user', [
            '_token' => $this->tokenFromCurrentResponse('admin_user_state'),
            'active' => '0',
        ]);
        self::assertResponseRedirects('/admin/users/'.$targetId);

        $this->entityManager->clear();
        $persistedOrder = $this->entityManager->find(Order::class, $orderId);
        $persistedUser = $this->entityManager->find(User::class, $targetId);
        self::assertInstanceOf(Order::class, $persistedOrder);
        self::assertInstanceOf(User::class, $persistedUser);
        self::assertSame($targetId, $persistedOrder->getUser()->getId());
        self::assertFalse($persistedUser->isActive());
    }

    public function testDuplicateUsernameReturnsValidationResponseAndDoesNotCreateSecondUser(): void
    {
        $admin = $this->createUser('admin', UserRole::ADMIN);
        $existing = $this->createUser('existing', UserRole::USER);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm('Create user', [
            'username' => $existing->getUsername(),
            'role' => UserRole::USER->value,
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'already exists');
        self::assertCount(2, $this->entityManager->getRepository(User::class)->findAll());
    }

    public function testDirectMutationWithoutCsrfIsForbidden(): void
    {
        $admin = $this->createUser('admin', UserRole::ADMIN);
        $target = $this->createUser('target', UserRole::USER);
        $this->client->loginUser($admin);

        $this->client->request('POST', '/admin/users/'.$target->getId().'/status', [
            'active' => '0',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSelfRoleChangeIsRejected(): void
    {
        $admin = $this->createUser('admin', UserRole::ADMIN);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users/'.$admin->getId().'/edit');
        $this->client->submitForm('Save changes', [
            'username' => 'admin',
            'role' => UserRole::USER->value,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'cannot change your own role');
    }

    private function createUser(string $username, UserRole $role): User
    {
        $user = new User($username.'-'.bin2hex(random_bytes(4)));
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'correct-password'));
        $user->setRoles([$role->value]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    private function tokenFromCurrentResponse(string $id): string
    {
        $crawler = $this->client->getCrawler();
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        self::assertNotNull($token);
        return $token;
    }
}
