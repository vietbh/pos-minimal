<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerControllerTest extends WebTestCase
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

    public function testAnonymousCustomerDetailRedirectsToLogin(): void
    {
        $this->client->request('GET', '/app/customers/1');
        self::assertResponseRedirects('/auth/login');
    }

    public function testAuthenticatedCustomerDetailShowsDebtAndOrderSections(): void
    {
        $this->loginAs('cashier');
        $customer = new \App\Domain\Customer\Customer('Detail HTTP Customer', '0909000001');
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->request('GET', '/app/customers/'.$customer->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Detail HTTP Customer');
        self::assertSelectorTextContains('#debt-summary-title', 'Công nợ');
        self::assertSelectorTextContains('#debt-history-title', 'Lịch sử công nợ');
        self::assertSelectorTextContains('#order-history-title', 'Đơn hàng gần đây');
        self::assertSelectorTextContains('.customer-empty', 'chưa có khoản công nợ nào');
    }

    public function testUnknownCustomerReturnsNotFound(): void
    {
        $this->loginAs('cashier');
        $this->client->request('GET', '/app/customers/999999');
        self::assertResponseStatusCodeSame(404);
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
