<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Checkout;

use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Order\Order;
use App\Domain\Order\Payment;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pcntl')]
final class CheckoutIdempotencyConcurrencyTest extends IntegrationTestCase
{
    public function testConcurrentSameKeyProducesExactlyOneCheckout(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'checkout-concurrent-'.bin2hex(
                random_bytes(8),
            ),
        );

        $product = new Product(
            'Concurrent Checkout Product',
            Money::fromDecimal('100000.00'),
        );

        $product->setStockQuantityForAdjustment(10);

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->flush();

        self::assertNotNull($user->getId());
        self::assertNotNull($product->getId());

        $userId = $user->getId();
        $productId = $product->getId();

        $idempotencyKey = 'checkout-concurrent-'
            .bin2hex(random_bytes(16));

        $startFile = tempnam(
            sys_get_temp_dir(),
            'checkout-start-',
        );

        $resultA = tempnam(
            sys_get_temp_dir(),
            'checkout-result-a-',
        );

        $resultB = tempnam(
            sys_get_temp_dir(),
            'checkout-result-b-',
        );

        self::assertNotFalse($startFile);
        self::assertNotFalse($resultA);
        self::assertNotFalse($resultB);

        unlink($startFile);
        unlink($resultA);
        unlink($resultB);

        $worker = __DIR__.'/../../../../Support/CheckoutConcurrencyWorker.php';

        $commandA = [
            PHP_BINARY,
            $worker,
            (string) $userId,
            (string) $productId,
            $idempotencyKey,
            $startFile,
            $resultA,
        ];

        $commandB = [
            PHP_BINARY,
            $worker,
            (string) $userId,
            (string) $productId,
            $idempotencyKey,
            $startFile,
            $resultB,
        ];

        $processA = $this->startProcess($commandA);
        $processB = $this->startProcess($commandB);

        /*
         * Both workers are booted and waiting before the gate opens.
         */
        $this->waitUntilFileExists(
            $resultA.'.ready',
            10,
            $processA,
        );

        $this->waitUntilFileExists(
            $resultB.'.ready',
            10,
            $processB,
        );
        /*
         * Release both processes at approximately the same time.
         */
        touch($startFile);

        $outputA = $this->waitForProcess(
            $processA,
        );

        $outputB = $this->waitForProcess(
            $processB,
        );

        self::assertTrue(
            $outputA['exitCode'] === 0,
            $outputA['stderr'],
        );

        self::assertTrue(
            $outputB['exitCode'] === 0,
            $outputB['stderr'],
        );

        $entityManager->clear();

        /*
         * -------------------------------------------------------------
         * BUSINESS RESULT
         * -------------------------------------------------------------
         */

        self::assertSame(
            1,
            $this->countOrders(
                $entityManager,
                $userId,
            ),
            'Concurrent same-key checkout must create exactly one order.',
        );

        self::assertSame(
            1,
            $this->countPayments(
                $entityManager,
                $userId,
            ),
            'Concurrent same-key checkout must create exactly one payment.',
        );

        self::assertSame(
            1,
            $this->countSaleMovements(
                $entityManager,
                $productId,
            ),
            'Concurrent same-key checkout must create exactly one SALE movement.',
        );

        /*
         * -------------------------------------------------------------
         * STOCK
         * -------------------------------------------------------------
         */

        /** @var Product|null $persistedProduct */
        $persistedProduct = $entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($persistedProduct);

        self::assertSame(
            9,
            $persistedProduct->getStockQuantity(),
            'Stock must be deducted exactly once.',
        );

        /*
         * -------------------------------------------------------------
         * IDEMPOTENCY
         * -------------------------------------------------------------
         */

        $records = $entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(IdempotencyRecord::class, 'r')
            ->where('r.idempotencyKey = :key')
            ->setParameter('key', $idempotencyKey)
            ->getQuery()
            ->getResult();

        self::assertCount(
            1,
            $records,
            'Concurrent same-key requests must produce one idempotency record.',
        );

        $record = $records[0];

        self::assertSame(
            IdempotencyStatus::COMPLETED,
            $record->getStatus(),
        );

        self::assertSame(
            200,
            $record->getResponseStatus(),
        );

        self::assertNotNull(
            $record->getResponseBody(),
        );

        /*
         * At least one worker must have reported EXECUTE.
         *
         * The other may report IN_PROGRESS depending on timing.
         *
         * The database assertions above are the authoritative proof
         * that only one business mutation occurred.
         */
        $results = [
            json_decode(
                file_get_contents($resultA),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
            json_decode(
                file_get_contents($resultB),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        ];

        $successful = array_filter(
            $results,
            static fn(array $result): bool =>
                ($result['status'] ?? null) === 'success',
        );

        self::assertCount(
            1,
            $successful,
            'Exactly one concurrent request must execute checkout.',
        );
    }

    /**
     * @param list<string> $command
     *
     * @return array
     */
    private function startProcess(array $command)
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptorSpec,
            $pipes,
            dirname(__DIR__, 5),
            [
                'APP_ENV' => 'test',
                'APP_DEBUG' => '1',
            ],
        );

        if (!is_resource($process)) {
            self::fail(
                'Unable to start concurrency worker.',
            );
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param array{
     *     process: resource,
     *     stdout: resource,
     *     stderr: resource
     * } $process
     *
     * @return array{
     *     exitCode: int,
     *     stdout: string,
     *     stderr: string
     * }
     */
    private function waitForProcess(array $process): array
    {
        $stdout = stream_get_contents($process['stdout']);
        $stderr = stream_get_contents($process['stderr']);

        fclose($process['stdout']);
        fclose($process['stderr']);

        $exitCode = proc_close($process['process']);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
        ];
    }

    private function waitUntilFileExists(
        string $path,
        int $timeoutSeconds = 10,
        ?array $process = null,
    ): void {
        $deadline = microtime(true) + $timeoutSeconds;

        while (!file_exists($path)) {
            if ($process !== null) {
                $status = proc_get_status($process['process']);

                if ($status['running'] === false) {
                    $stdout = stream_get_contents($process['stdout']);
                    $stderr = stream_get_contents($process['stderr']);

                    self::fail(
                        sprintf(
                            "Concurrency worker exited before readiness file existed.\n".
                            "Path: %s\n".
                            "Exit code: %s\n".
                            "STDOUT:\n%s\n".
                            "STDERR:\n%s",
                            $path,
                            (string) $status['exitcode'],
                            $stdout ?: '<empty>',
                            $stderr ?: '<empty>',
                        ),
                    );
                }
            }

            if (microtime(true) >= $deadline) {
                self::fail(
                    sprintf(
                        'Timed out waiting for %s.',
                        $path,
                    ),
                );
            }

            usleep(10_000);
        }
    }

    /**
     * @return array<string, int>
     */
    private function countOrders(
        EntityManagerInterface $entityManager,
        int $userId,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->where('IDENTITY(o.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPayments(
        EntityManagerInterface $entityManager,
        int $userId,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Payment::class, 'p')
            ->where('IDENTITY(p.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countSaleMovements(
        EntityManagerInterface $entityManager,
        int $productId,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(StockMovement::class, 'm')
            ->where('IDENTITY(m.product) = :productId')
            ->andWhere('m.type = :type')
            ->setParameter('productId', $productId)
            ->setParameter('type', StockMovementType::SALE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
