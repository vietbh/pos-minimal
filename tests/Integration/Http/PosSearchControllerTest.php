<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PosSearchControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testProductSearchRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app/pos/products?q=coffee');
        self::assertResponseRedirects('/auth/login');
    }

    public function testCustomerSearchIsAvailableToAuthenticatedPosUser(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }

        $user = new User('pos-search-'.bin2hex(random_bytes(4)));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client->loginUser($user);
        $client->request('GET', '/app/pos/customers?q=customer');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertIsArray($payload);
        self::assertArrayHasKey('data', $payload);
    }
}
