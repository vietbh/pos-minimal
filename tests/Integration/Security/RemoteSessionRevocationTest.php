<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Domain\User\Enum\SessionStatus;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RemoteSessionRevocationTest extends WebTestCase
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

    public function testRemoteRevokeInvalidatesCurrentAuthenticatedBrowserOnNextRequest(): void
    {
        $admin = $this->createUser('remote-admin', UserRole::ADMIN);
        $target = $this->createUser('remote-target', UserRole::USER);

        $adminClient = static::createClient();
        $adminClient->disableReboot();
        $userClient = static::createClient();
        $userClient->disableReboot();

        $this->login($adminClient, $admin->getUsername());
        $this->login($userClient, $target->getUsername());

        $userClient->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $target = $this->entityManager->find(User::class, $target->getId());
        self::assertInstanceOf(User::class, $target);

        $sessionRepository = static::getContainer()->get(UserSessionRepositoryInterface::class);
        $sessions = $sessionRepository->findByUser($target, 20);
        self::assertCount(1, $sessions);
        self::assertSame(SessionStatus::ACTIVE, $sessions[0]->getStatus());
        $sessionId = $sessions[0]->getId();
        self::assertNotNull($sessionId);

        $csrf = static::getContainer()
            ->get(CsrfTokenManagerInterface::class)
            ->getToken('admin_user_state')
            ->getValue();

        $adminClient->request(
            'POST',
            sprintf('/admin/users/%d/sessions/%d/revoke', $target->getId(), $sessionId),
            ['_token' => $csrf],
        );

        self::assertSame(302, $adminClient->getResponse()->getStatusCode());
        self::assertSame('/admin/users/'.$target->getId(), $adminClient->getResponse()->headers->get('Location'));

        $this->entityManager->clear();
        $revoked = $this->entityManager->getRepository(\App\Domain\User\UserSession::class)->find($sessionId);
        self::assertNotNull($revoked);
        self::assertSame(SessionStatus::REVOKED, $revoked->getStatus());

        $userClient->request('GET', '/');
        self::assertSame(302, $userClient->getResponse()->getStatusCode());
        self::assertSame('/auth/login', $userClient->getResponse()->headers->get('Location'));
    }

    public function testRemoteRevokeAlsoInvalidatesRememberMeCookie(): void
    {
        $admin = $this->createUser('remember-admin', UserRole::ADMIN);
        $target = $this->createUser('remember-target', UserRole::USER);

        $adminClient = static::createClient();
        $adminClient->disableReboot();
        $userClient = static::createClient();
        $userClient->disableReboot();

        $this->login($adminClient, $admin->getUsername());
        $this->login($userClient, $target->getUsername(), true);

        $rememberCookie = $userClient->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($rememberCookie, 'Remember-me cookie must be issued by the login flow.');

        $this->entityManager->clear();
        $target = $this->entityManager->find(User::class, $target->getId());
        self::assertInstanceOf(User::class, $target);
        $sessionRepository = static::getContainer()->get(UserSessionRepositoryInterface::class);
        $sessions = $sessionRepository->findByUser($target, 20);
        self::assertCount(1, $sessions);
        $sessionId = $sessions[0]->getId();
        self::assertNotNull($sessionId);

        $csrf = static::getContainer()
            ->get(CsrfTokenManagerInterface::class)
            ->getToken('admin_user_state')
            ->getValue();

        $adminClient->request(
            'POST',
            sprintf('/admin/users/%d/sessions/%d/revoke', $target->getId(), $sessionId),
            ['_token' => $csrf],
        );
        self::assertSame(302, $adminClient->getResponse()->getStatusCode());
        self::assertSame('/admin/users/'.$target->getId(), $adminClient->getResponse()->headers->get('Location'));

        // Remove only the normal session cookie. Keep REMEMBERME so this request
        // proves that remote revoke invalidates the long-lived credential too.
        foreach ($userClient->getCookieJar()->all() as $cookie) {
            if (strtoupper($cookie->getName()) !== 'REMEMBERME') {
                $userClient->getCookieJar()->expire($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        $userClient->request('GET', '/');
        self::assertSame(302, $userClient->getResponse()->getStatusCode());
        self::assertSame('/auth/login', $userClient->getResponse()->headers->get('Location'));
    }

    private function login(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $username,
        bool $rememberMe = false,
    ): void {
        $client->request('GET', '/auth/login');

        $fields = [
            '_username' => $username,
            '_password' => 'correct-password',
        ];
        if ($rememberMe) {
            $fields['_remember_me'] = '1';
        }

        $client->submitForm('Sign in', $fields);
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
