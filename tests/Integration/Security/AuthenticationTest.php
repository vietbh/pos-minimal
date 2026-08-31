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

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();

        $client->submitForm('Login', [
            '_username' => 'cashier',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser(
            'cashier',
            'correct-password',
        );

        $client = $this->client;

        $client->request('GET', '/login');

        $client->submitForm('Login', [
            '_username' => 'cashier',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Invalid credentials.',
        );
    }

    public function testUnknownUserIsRejected(): void
    {
        $client = $this->client;

        $client->request('GET', '/login');

        $client->submitForm('Login', [
            '_username' => 'does-not-exist',
            '_password' => 'anything',
        ]);

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Invalid credentials.',
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

        $client->request('GET', '/login');

        $client->submitForm('Login', [
            '_username' => 'inactive',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertSelectorTextContains(
            'body',
            'Invalid credentials.',
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
            '/login',
            [
                '_username' => 'cashier',
                '_password' => 'correct-password',
            ],
        );

        self::assertResponseRedirects('/login');
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
