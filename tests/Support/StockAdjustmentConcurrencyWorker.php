<?php

declare(strict_types=1);

use App\Application\Product\Command\AdjustStock\AdjustStockHandler;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
}

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer();

$userId = (int) ($argv[1] ?? 0);
$productId = (int) ($argv[2] ?? 0);
$quantityChange = (int) ($argv[3] ?? 0);
$key = (string) ($argv[4] ?? '');
$startFile = (string) ($argv[5] ?? '');
$resultFile = (string) ($argv[6] ?? '');

if ($userId <= 0 || $productId <= 0 || $quantityChange === 0 || $key === '' || $startFile === '' || $resultFile === '') {
    fwrite(STDERR, "Invalid worker arguments.\n");
    exit(2);
}

touch($resultFile.'.ready');
$deadline = microtime(true) + 30;

while (!file_exists($startFile)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($resultFile, json_encode(['status' => 'timeout'], JSON_THROW_ON_ERROR));
        exit(3);
    }
    usleep(1000);
}

/** @var RuntimeActorContextProvider $provider */
$provider = $container->get(RuntimeActorContextProvider::class);
$provider->set(new ActorContext(
    userId: $userId,
    requestId: 'concurrent-stock-worker',
));

/** @var AdjustStockHandler $handler */
$handler = $container->get(AdjustStockHandler::class);

try {
    $result = $handler(new AdjustStockInput(
        productId: $productId,
        quantityChange: $quantityChange,
        reason: 'Concurrent stock test',
        idempotencyKey: $key,
    ));

    file_put_contents($resultFile, json_encode([
        'status' => 'success',
        'quantityBefore' => $result->quantityBefore,
        'quantityAfter' => $result->quantityAfter,
        'stockMovementId' => $result->stockMovementId,
    ], JSON_THROW_ON_ERROR));
} catch (\Throwable $exception) {
    file_put_contents($resultFile, json_encode([
        'status' => 'error',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
}

$provider->clear();
$kernel->shutdown();
