<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool = new SchemaTool(
            $this->entityManager,
        );

        /*
         * Each integration test starts with a completely clean schema.
         *
         * This deliberately does not use an outer transaction because
         * application code owns explicit transaction boundaries.
         */
        $schemaTool->dropDatabase();

        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();

        parent::tearDown();
    }
}
