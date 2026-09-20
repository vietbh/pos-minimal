<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = $this->client->getContainer();

        $this->entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $this->passwordHasher = $container->get(
            UserPasswordHasherInterface::class,
        );

        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool = new SchemaTool(
            $this->entityManager,
        );

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

    public function testValidCredentialsAuthenticate(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $client = $this->client;

        $client->request('GET', '/auth/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action=\"/auth/login\"]');
        self::assertSelectorExists('input[name=\"_csrf_token\"]');
        self::assertSelectorExists('input[name=\"_remember_me\"]');
        self::assertSelectorTextContains('body', 'Access your account to continue.');

        $client->submitForm('Sign in', [
            '_username' => 'cashier',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hello, cashier');
        self::assertSelectorExists('nav[aria-label="Primary navigation"]');
        self::assertSelectorExists('a[aria-current="page"]');
        self::assertSelectorExists('form[action="/auth/logout"]');
    }

    public function testRememberMeCheckboxIsAvailable(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $this->client->request('GET', '/auth/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_remember_me"]');
        self::assertSelectorTextContains(
            'body',
            'Remember me on this device',
        );
    }

    public function testRememberMeSetsRememberMeCookieWhenSelected(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $client = $this->client;
        $client->request('GET', '/auth/login');
        $client->submitForm('Sign in', [
            '_username' => 'cashier',
            '_password' => 'correct-password',
            '_remember_me' => '1',
        ]);

        self::assertResponseRedirects('/');

        $cookies = $client->getResponse()->headers->getCookies();
        $rememberCookieNames = array_values(array_filter(
            array_map(
                static fn ($cookie): string => $cookie->getName(),
                $cookies,
            ),
            static fn (string $name): bool => str_contains(strtolower($name), 'remember'),
        ));

        self::assertNotEmpty($rememberCookieNames);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $client = $this->client;

        $client->request('GET', '/auth/login');

        $client->submitForm('Sign in', [
            '_username' => 'cashier',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/auth/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Sign-in failed',
        );
    }

    public function testUnknownUserIsRejected(): void
    {
        $client = $this->client;

        $client->request('GET', '/auth/login');

        $client->submitForm('Sign in', [
            '_username' => 'does-not-exist',
            '_password' => 'anything',
        ]);

        self::assertResponseRedirects('/auth/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Sign-in failed',
        );
    }

    public function testInactiveUserIsRejected(): void
    {
        $user = $this->createUser(
            'inactive',
            'correct-password',
        );

        $user->deactivate();

        $this->entityManager->flush();

        $client = $this->client;

        $client->request('GET', '/auth/login');

        $client->submitForm('Sign in', [
            '_username' => 'inactive',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/auth/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Sign-in failed',
        );
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $client = $this->client;

        $client->request(
            'POST',
            '/auth/login',
            [
                '_username' => 'cashier',
                '_password' => 'correct-password',
            ],
        );

        self::assertResponseRedirects('/auth/login');
    }

    public function testAnonymousHomeRedirectsToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/auth/login');
    }

    public function testAuthenticatedHomeDoesNotOpenPosAutomatically(): void
    {
        $this->createUser('cashier', 'correct-password');
        $client = $this->client;

        $client->request('GET', '/auth/login');
        $client->submitForm('Sign in', [
            '_username' => 'cashier',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Application');
        self::assertSelectorTextNotContains('body', 'Complete sale');
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $this->createUser('cashier', 'correct-password');
        $client = $this->client;

        $client->request('GET', '/auth/login');
        $client->submitForm('Sign in', [
            '_username' => 'cashier',
            '_password' => 'correct-password',
        ]);
        self::assertResponseRedirects('/');

        $client->request('GET', '/auth/logout');
        self::assertResponseRedirects('/auth/login');

        $client->request('GET', '/');
        self::assertResponseRedirects('/auth/login');
    }

    private function createUser(
        string $username,
        string $password,
    ): User {
        $user = new User($username);

        $user->setPasswordHash(
            $this->passwordHasher->hashPassword(
                $user,
                $password,
            ),
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
