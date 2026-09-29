<?php

declare(strict_types=1);

use App\Application\User\UserManagementService;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';

(new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__, 2).'/.env');
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

require dirname(__DIR__, 2).'/src/Kernel.php';

$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer();

[$actorId, $targetId, $roleValue, $startFile, $resultFile] = array_slice($argv, 1);

@touch($resultFile.'.ready');
$deadline = microtime(true) + 30;
while (!file_exists($startFile)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($resultFile, json_encode(['status' => 'error', 'message' => 'start timeout']));
        exit(1);
    }
    usleep(1000);
}

try {
    /** @var UserRepositoryInterface $users */
    $users = $container->get(UserRepositoryInterface::class);
    /** @var UserManagementService $service */
    $service = $container->get(UserManagementService::class);
    $actor = $users->findById((int) $actorId);
    if ($actor === null) {
        throw new RuntimeException('actor not found');
    }
    $role = UserRole::from($roleValue);
    $service->update($actor, (int) $targetId, 'concurrent-target', $role);
    file_put_contents($resultFile, json_encode(['status' => 'success', 'role' => $roleValue]));
    exit(0);
} catch (Throwable $e) {
    file_put_contents($resultFile, json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    exit(0);
} finally {
    $kernel->shutdown();
}
