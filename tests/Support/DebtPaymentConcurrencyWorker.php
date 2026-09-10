<?php

declare(strict_types=1);

use App\Application\Debt\Command\PayDebt\PayDebtHandlerEntryPoint;
use App\Application\Debt\Command\PayDebt\PayDebtInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$resultFile = (string) ($argv[6] ?? '');

try {
    if (class_exists(Dotenv::class)) {
        (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
    }

    $_SERVER['APP_ENV'] = 'test';
    $_ENV['APP_ENV'] = 'test';

    $kernel = new App\Kernel('test', true);
    $kernel->boot();
    $container = $kernel->getContainer();

    $actorId = (int) ($argv[1] ?? 0);
    $debtId = (int) ($argv[2] ?? 0);
    $amount = (string) ($argv[3] ?? '');
    $idempotencyKey = (string) ($argv[4] ?? '');
    $startFile = (string) ($argv[5] ?? '');

    if (
        $actorId <= 0
        || $debtId <= 0
        || $amount === ''
        || $idempotencyKey === ''
        || $startFile === ''
        || $resultFile === ''
    ) {
        throw new InvalidArgumentException('Invalid worker arguments.');
    }

    /** @var RuntimeActorContextProvider $actorContextProvider */
    $actorContextProvider = $container->get(RuntimeActorContextProvider::class);
    $actorContextProvider->set(new ActorContext(
        userId: $actorId,
        sessionId: null,
        requestId: 'debt-payment-concurrency-worker-'.bin2hex(random_bytes(4)),
    ));

    /** @var PayDebtHandlerEntryPoint $entryPoint */
    $entryPoint = $container->get(PayDebtHandlerEntryPoint::class);

    // Ready means the complete application entry point is resolvable.
    touch($resultFile.'.ready');

    $deadline = microtime(true) + 30;
    while (!file_exists($startFile)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency start gate timed out.');
        }
        usleep(1000);
    }

    $result = $entryPoint->handle(new PayDebtInput(
        debtId: $debtId,
        amount: $amount,
        idempotencyKey: $idempotencyKey,
    ));

    file_put_contents($resultFile, json_encode([
        'status' => 'success',
        'debtId' => $result->debtId,
        'paymentId' => $result->paymentId,
        'amount' => $result->amount,
        'paidAmount' => $result->paidAmount,
        'remainingAmount' => $result->remainingAmount,
        'debtStatus' => $result->status,
    ], JSON_THROW_ON_ERROR));

    $actorContextProvider->clear();
    $kernel->shutdown();
    exit(0);
} catch (Throwable $exception) {
    if ($resultFile !== '') {
        @file_put_contents($resultFile, json_encode([
            'status' => 'error',
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR));
    }

    // Expected domain/idempotency rejections are returned as a structured result.
    // The parent test decides whether the rejection is expected.
    exit(0);
}
