<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $storageRoot,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => 'ok', 'storage' => 'ok'];

        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            $checks['database'] = 'failed';
        }

        if (!is_dir($this->storageRoot) || !is_writable($this->storageRoot)) {
            $checks['storage'] = 'failed';
        }

        $healthy = !in_array('failed', $checks, true);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'unhealthy', 'checks' => $checks],
            $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
