<?php

declare(strict_types=1);

use App\Application\Common\Idempotency\IdempotencyDecisionType;
use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Shared\ValueObject\Money;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    (new Dotenv())->bootEnv(
        dirname(__DIR__, 2).'/.env',
    );
}

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

$kernel = new App\Kernel(
    'test',
    true,
);

$kernel->boot();

$container = $kernel->getContainer();

$userId = (int) ($argv[1] ?? 0);
$productId = (int) ($argv[2] ?? 0);
$idempotencyKey = (string) ($argv[3] ?? '');
$startFile = (string) ($argv[4] ?? '');
$resultFile = (string) ($argv[5] ?? '');

if (
    $userId <= 0
    || $productId <= 0
    || $idempotencyKey === ''
    || $startFile === ''
    || $resultFile === ''
) {
    fwrite(
        STDERR,
        "Invalid worker arguments.\n",
    );

    exit(2);
}

/** @var RuntimeActorContextProvider $actorContextProvider */
$actorContextProvider = $container->get(
    RuntimeActorContextProvider::class,
);

$actorContextProvider->set(
    new ActorContext(
        userId: $userId,
        sessionId: null,
        requestId: 'concurrent-checkout-worker',
    ),
);

$readyFile = $resultFile.'.ready';

touch($readyFile);

$deadline = microtime(true) + 30;

while (!file_exists($startFile)) {
    if (microtime(true) >= $deadline) {
        file_put_contents(
            $resultFile,
            json_encode(
                [
                    'status' => 'timeout',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        exit(3);
    }

    usleep(1_000);
}

$input = new CheckoutInput(
    items: [
        new CheckoutItemInput(
            productId: $productId,
            quantity: 1,
        ),
    ],
    customerId: null,
    payment: new CheckoutPaymentInput(
        method: PaymentMethod::CASH,
        amount: Money::fromDecimal('100000.00'),
    ),
    note: 'Concurrent checkout test',
    idempotencyKey: $idempotencyKey,
);

/** @var CheckoutHandlerEntryPoint $entryPoint */
$entryPoint = $container->get(
    CheckoutHandlerEntryPoint::class,
);

try {
    $result = $entryPoint->handle($input);

    file_put_contents(
        $resultFile,
        json_encode(
            [
                'status' => 'success',
                'orderId' => $result->orderId,
                'orderNumber' => $result->orderNumber,
            ],
            JSON_THROW_ON_ERROR,
        ),
    );
} catch (\Throwable $exception) {
    file_put_contents(
        $resultFile,
        json_encode(
            [
                'status' => 'error',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
            JSON_THROW_ON_ERROR,
        ),
    );
}

$actorContextProvider->clear();

$kernel->shutdown();
