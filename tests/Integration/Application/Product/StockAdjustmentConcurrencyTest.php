<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Product;

use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;

final class StockAdjustmentConcurrencyTest extends IntegrationTestCase
{
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User('stock-concurrent-user-'.bin2hex(random_bytes(4)));
        $this->product = new Product(
            'Concurrent Stock Product',
            Money::fromDecimal('10000.00'),
        );
        $this->product->setStockQuantityForAdjustment(10);

        $this->entityManager->persist($this->user);
        $this->entityManager->persist($this->product);
        $this->entityManager->flush();
    }

    public function testConcurrentPositiveAdjustmentsDoNotLoseUpdates(): void
    {
        $results = $this->runWorkers(
            [5, 3],
            ['concurrent-plus-a', 'concurrent-plus-b'],
        );

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(18, $product->getStockQuantity());

        self::assertSame(
            2,
            $this->entityManager->getRepository(StockMovement::class)
                ->count([]),
        );
        self::assertSame(
            2,
            $this->entityManager->getRepository(AuditLog::class)
                ->count([]),
        );

        self::assertCount(2, array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'success',
        ));
    }

    public function testConcurrentOverdrawAllowsOnlyOneMutation(): void
    {
        $this->product->setStockQuantityForAdjustment(2);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $results = $this->runWorkers(
            [-2, -2],
            ['concurrent-overdraw-a', 'concurrent-overdraw-b'],
        );

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(0, $product->getStockQuantity());

        self::assertSame(1, $this->entityManager->getRepository(StockMovement::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(AuditLog::class)->count([]));

        self::assertSame(
            1,
            count(array_filter(
                $results,
                static fn (array $result): bool => $result['status'] === 'success',
            )),
        );
        self::assertSame(
            1,
            count(array_filter(
                $results,
                static fn (array $result): bool => $result['status'] === 'error',
            )),
        );
    }

    public function testConcurrentSameKeyProducesOneMutation(): void
    {
        $key = 'concurrent-same-key-'.bin2hex(random_bytes(8));

        $results = $this->runWorkers(
            [5, 5],
            [$key, $key],
        );

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(15, $product->getStockQuantity());

        self::assertSame(1, $this->entityManager->getRepository(StockMovement::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(AuditLog::class)->count([]));

        self::assertSame(
            1,
            count(array_filter(
                $results,
                static fn (array $result): bool => $result['status'] === 'success',
            )),
        );
    }

    /** @return list<array<string,mixed>> */
    private function runWorkers(array $deltas, array $keys): array
    {
        $dir = sys_get_temp_dir().'/mobile-pos-stock-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);

        $start = $dir.'/start';
        $processes = [];
        $resultFiles = [];

        try {
            foreach ($deltas as $index => $delta) {
                $resultFile = $dir.'/result-'.$index.'.json';
                $resultFiles[] = $resultFile;

                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 3).'/Support/StockAdjustmentConcurrencyWorker.php',
                    (string) $this->userId(),
                    (string) $this->productId(),
                    (string) $delta,
                    $keys[$index],
                    $start,
                    $resultFile,
                ];

                $descriptor = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 4));
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes[1], $pipes[2]];
            }

            $deadline = microtime(true) + 20;
            foreach ($resultFiles as $resultFile) {
                while (!file_exists($resultFile.'.ready')) {
                    if (microtime(true) >= $deadline) {
                        self::fail('Concurrency workers did not become ready.');
                    }
                    usleep(1000);
                }
            }

            touch($start);

            $results = [];
            foreach ($processes as [$process, $stdout, $stderr]) {
                $output = trim(stream_get_contents($stdout));
                $errorOutput = trim(stream_get_contents($stderr));

                fclose($stdout);
                fclose($stderr);

                $exitCode = proc_close($process);

                if ($exitCode !== 0 && $output === '') {
                    self::fail('Concurrency worker failed: '.$errorOutput);
                }
            }

            foreach ($resultFiles as $resultFile) {
                $deadline = microtime(true) + 20;
                while (!file_exists($resultFile)) {
                    if (microtime(true) >= $deadline) {
                        self::fail('Concurrency worker did not produce a result.');
                    }
                    usleep(1000);
                }

                $decoded = json_decode(
                    (string) file_get_contents($resultFile),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                self::assertIsArray($decoded);
                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($resultFiles as $resultFile) {
                @unlink($resultFile);
                @unlink($resultFile.'.ready');
            }
            @unlink($start);
            @rmdir($dir);
        }
    }

    private function userId(): int
    {
        $id = $this->user->getId();
        self::assertNotNull($id);
        return $id;
    }

    private function productId(): int
    {
        $id = $this->product->getId();
        self::assertNotNull($id);
        return $id;
    }
}
