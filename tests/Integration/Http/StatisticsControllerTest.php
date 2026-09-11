<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatisticsControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        $this->entityManager = self::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $tool = new SchemaTool($this->entityManager);
        $tool->dropDatabase();

        if ($metadata !== []) {
            $tool->createSchema($metadata);
        }
    }

    public function testStatisticsRequiresStatisticsPermission(): void
    {
        $user = new User('stats-user@example.test');
        $user->grantRole(UserRole::USER);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/app/statistics');

        self::assertResponseStatusCodeSame(403);
    }

    public function testStatisticsRouteExists(): void
    {
        $routes=self::getContainer()->get('router')->getRouteCollection();
        self::assertNotNull($routes->get('statistics_index'));
    }
}
