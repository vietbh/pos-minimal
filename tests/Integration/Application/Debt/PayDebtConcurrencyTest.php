<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Debt;

use App\Application\Common\Idempotency\IdempotencyConflict;
use App\Application\Debt\Command\PayDebt\PayDebtHandlerEntryPoint;
use App\Application\Debt\Command\PayDebt\PayDebtInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Customer\Customer;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Enum\DebtStatus;
use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Order\Order;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Random\RandomException;

#[RequiresPhpExtension('pcntl')]
final class PayDebtConcurrencyTest extends IntegrationTestCase
{
    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     */
    public function testConcurrentDifferentKeysCannotOverpayDebt(): void
    {
        [$user, $debt] = $this->createDebt('100.00');

        $results = $this->runWorkers(
            debtId: $this->idOf($debt),
            userId: $this->idOf($user),
            amounts: ['60.00', '60.00'],
            keys: [
                'debt-race-a-'.bin2hex(random_bytes(8)),
                'debt-race-b-'.bin2hex(random_bytes(8)),
            ],
        );

        $this->entityManager->clear();

        /** @var Debt|null $persistedDebt */
        $persistedDebt = $this->entityManager->find(Debt::class, $this->idOf($debt));
        self::assertNotNull($persistedDebt);

        self::assertSame('60.00', $persistedDebt->getPaidAmount()->toDecimal());
        self::assertSame('40.00', $persistedDebt->getRemainingAmount()->toDecimal());
        self::assertSame(DebtStatus::PARTIALLY_PAID, $persistedDebt->getStatus());
        self::assertCount(1, $persistedDebt->getPayments());

        self::assertCount(1, array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'success',
        ));
        self::assertCount(1, array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'error',
        ));
    }

    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     */
    public function testConcurrentSameKeyCreatesExactlyOnePaymentAndOneIdempotencyRecord(): void
    {
        [$user, $debt] = $this->createDebt('100.00');
        $key = 'debt-same-key-'.bin2hex(random_bytes(8));

        $results = $this->runWorkers(
            debtId: $this->idOf($debt),
            userId: $this->idOf($user),
            amounts: ['60.00', '60.00'],
            keys: [$key, $key],
        );

        $this->entityManager->clear();

        /** @var Debt|null $persistedDebt */
        $persistedDebt = $this->entityManager->find(Debt::class, $this->idOf($debt));
        self::assertNotNull($persistedDebt);
        self::assertSame('60.00', $persistedDebt->getPaidAmount()->toDecimal());
        self::assertCount(1, $persistedDebt->getPayments());

        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(IdempotencyRecord::class, 'r')
            ->where('r.idempotencyKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $records);

        $record = $records[0];

        self::assertInstanceOf(IdempotencyRecord::class, $record);
        self::assertSame(IdempotencyStatus::COMPLETED, $record->getStatus());
        self::assertSame(200, $record->getResponseStatus());
        self::assertNotNull($record->getResponseBody());

        self::assertCount(1, $records);
        self::assertSame(
            $this->idOf($user),
            $records[0]->getUser()->getId(),
        );
        self::assertSame(200, $records[0]->getResponseStatus());
        self::assertNotNull($records[0]->getResponseBody());

        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['status'] ?? null) === 'success',
        ));

        $nonSuccesses = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['status'] ?? null) !== 'success',
        ));

        self::assertCount(1, $successes);
        self::assertCount(1, $nonSuccesses);

        $nonSuccess = $nonSuccesses[0];

        // The losing concurrent request may observe either the in-progress
        // reservation or the completed idempotent operation, depending on
        // transaction timing. The invariant is that it must not mutate debt
        // a second time.
        self::assertSame('error', $nonSuccess['status']);
        self::assertNotSame('', trim((string) ($nonSuccess['message'] ?? '')));
    }

    public function testSameKeyWithDifferentPayloadIsRejectedWithoutSecondPayment(): void
    {
        [$user, $debt] = $this->createDebt('100.00');
        $container = self::getContainer();

        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = $container->get(RuntimeActorContextProvider::class);
        $actorContextProvider->set(new ActorContext(
            userId: $this->idOf($user),
            sessionId: null,
            requestId: 'debt-payment-idempotency-test',
        ));

        /** @var PayDebtHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(PayDebtHandlerEntryPoint::class);
        $key = 'debt-payload-conflict-'.bin2hex(random_bytes(8));

        $first = $entryPoint->handle(new PayDebtInput(
            debtId: $this->idOf($debt),
            amount: '60.00',
            idempotencyKey: $key,
        ));

        self::assertSame('60.00', $first->amount);

        try {
            $entryPoint->handle(new PayDebtInput(
                debtId: $this->idOf($debt),
                amount: '40.00',
                idempotencyKey: $key,
            ));
            self::fail('Expected an idempotency conflict for a changed payload.');
        } catch (IdempotencyConflict) {
            // Expected: an idempotency key cannot be reused for another payload.
        } finally {
            $actorContextProvider->clear();
        }

        $this->entityManager->clear();
        /** @var Debt|null $persistedDebt */
        $persistedDebt = $this->entityManager->find(Debt::class, $this->idOf($debt));
        self::assertNotNull($persistedDebt);
        self::assertSame('60.00', $persistedDebt->getPaidAmount()->toDecimal());
        self::assertCount(1, $persistedDebt->getPayments());
    }

    public function testCompletedSameKeyReplaysExactOriginalResponseWithoutMutation(): void
    {
        [$user, $debt] = $this->createDebt('100.00');
        $container = self::getContainer();

        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = $container->get(RuntimeActorContextProvider::class);
        $actorContextProvider->set(new ActorContext(
            userId: $this->idOf($user),
            sessionId: null,
            requestId: 'debt-payment-replay-test',
        ));

        /** @var PayDebtHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(PayDebtHandlerEntryPoint::class);
        $key = 'debt-replay-'.bin2hex(random_bytes(8));

        $first = $entryPoint->handle(new PayDebtInput(
            debtId: $this->idOf($debt),
            amount: '100.00',
            idempotencyKey: $key,
        ));

        $second = $entryPoint->handle(new PayDebtInput(
            debtId: $this->idOf($debt),
            amount: '100.00',
            idempotencyKey: $key,
        ));

        self::assertSame($first->paymentId, $second->paymentId);
        self::assertSame($first->amount, $second->amount);
        self::assertSame($first->paidAmount, $second->paidAmount);
        self::assertSame($first->remainingAmount, $second->remainingAmount);
        self::assertSame($first->status, $second->status);

        $this->entityManager->clear();
        /** @var Debt|null $persistedDebt */
        $persistedDebt = $this->entityManager->find(Debt::class, $this->idOf($debt));
        self::assertNotNull($persistedDebt);
        self::assertCount(1, $persistedDebt->getPayments());
        self::assertSame(DebtStatus::PAID, $persistedDebt->getStatus());

        $actorContextProvider->clear();
    }

    /** @return array{0: User, 1: Debt} */
    private function createDebt(string $amount): array
    {
        $user = new User('debt-concurrent-user-'.bin2hex(random_bytes(8)));
        $customer = new Customer('Debt Concurrency Customer');
        $order = new Order(
            new OrderNumber('DEBT-CONC-'.bin2hex(random_bytes(8))),
            $user,
            $customer,
        );
        $debt = new Debt(
            $customer,
            $order,
            $user,
            Money::fromDecimal($amount),
        );

        $this->entityManager->persist($user);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($order);
        $this->entityManager->persist($debt);
        $this->entityManager->flush();

        return [$user, $debt];
    }

    /**
     * @param list<string> $amounts
     * @param list<string> $keys
     * @return list<array<string,mixed>>
     */
    private function runWorkers(
        int $debtId,
        int $userId,
        array $amounts,
        array $keys,
    ): array {
        self::assertCount(2, $amounts);
        self::assertCount(2, $keys);

        $dir = sys_get_temp_dir().'/mobile-pos-debt-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0700, true));

        $start = $dir.'/start';
        $processes = [];
        $resultFiles = [];

        try {
            foreach ([0, 1] as $index) {
                $resultFile = $dir.'/result-'.$index.'.json';
                $resultFiles[] = $resultFile;

                $command = [
                    PHP_BINARY,
                    dirname(__DIR__, 3).'/Support/DebtPaymentConcurrencyWorker.php',
                    (string) $userId,
                    (string) $debtId,
                    $amounts[$index],
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

            $deadline = microtime(true) + 30;
            foreach ($resultFiles as $resultFile) {
                while (!file_exists($resultFile.'.ready')) {
                    if (microtime(true) >= $deadline) {
                        $diagnostics = [];
                        foreach ($processes as $index => [$process, $stdout, $stderr]) {
                            $errorOutput = trim(stream_get_contents($stderr));
                            if ($errorOutput !== '') {
                                $diagnostics[] = 'worker-'.$index.': '.$errorOutput;
                            }
                            if (is_resource($process)) {
                                @proc_terminate($process);
                            }
                        }
                        self::fail(
                            'Debt payment concurrency workers did not become ready.'
                            .($diagnostics === [] ? '' : ' '.implode(' | ', $diagnostics))
                        );
                    }
                    usleep(1000);
                }
            }

            touch($start);

            foreach ($processes as [$process, $stdout, $stderr]) {
                stream_get_contents($stdout);
                $errorOutput = trim(stream_get_contents($stderr));
                fclose($stdout);
                fclose($stderr);
                $exitCode = proc_close($process);

                if ($exitCode !== 0) {
                    self::fail('Debt payment concurrency worker failed: '.$errorOutput);
                }
            }

            $results = [];
            foreach ($resultFiles as $resultFile) {
                $deadline = microtime(true) + 30;
                while (!file_exists($resultFile)) {
                    if (microtime(true) >= $deadline) {
                        self::fail('Debt payment concurrency worker did not produce a result.');
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

    private function idOf(object $entity): int
    {
        $id = $entity->getId();
        self::assertNotNull($id);
        return $id;
    }
}
