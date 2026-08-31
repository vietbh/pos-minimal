<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Product;

use App\Application\Common\Idempotency\IdempotencyConflict;
use App\Application\Product\Command\AdjustStock\AdjustStockHandler;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class StockAdjustmentTest extends IntegrationTestCase
{
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User('stock-user-'.bin2hex(random_bytes(4)));
        $this->product = new Product(
            'Stock Adjustment Product',
            Money::fromDecimal('10000.00'),
        );
        $this->product->setStockQuantityForAdjustment(10);

        $this->entityManager->persist($this->user);
        $this->entityManager->persist($this->product);
        $this->entityManager->flush();

        $userId = $this->user->getId();
        self::assertNotNull($userId);

        self::getContainer()
            ->get(RuntimeActorContextProvider::class)
            ->set(new ActorContext(
                userId: $userId,
                requestId: 'stock-adjustment-test',
            ));
    }

    protected function tearDown(): void
    {
        self::getContainer()
            ->get(RuntimeActorContextProvider::class)
            ->clear();

        parent::tearDown();
    }

    public function testPositiveAdjustmentPersistsProductMovementAndAudit(): void
    {
        $result = $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                5,
                'Restock',
                'stock-plus-'.bin2hex(random_bytes(8)),
            ),
        );

        self::assertSame(10, $result->quantityBefore);
        self::assertSame(5, $result->quantityChange);
        self::assertSame(15, $result->quantityAfter);

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(15, $product->getStockQuantity());

        $movements = $this->movements();
        self::assertCount(1, $movements);
        self::assertSame(StockMovementType::ADJUSTMENT, $movements[0]->getType());
        self::assertSame(10, $movements[0]->getQuantityBefore());
        self::assertSame(5, $movements[0]->getQuantityChange());
        self::assertSame(15, $movements[0]->getQuantityAfter());
        self::assertSame('Restock', $movements[0]->getReason());

        $audits = $this->audits();
        self::assertCount(1, $audits);
        self::assertSame('STOCK_ADJUSTED', $audits[0]->getAction());
        self::assertSame('Product', $audits[0]->getEntityType());
        self::assertSame((string) $this->productId(), $audits[0]->getEntityId());
        self::assertSame(10, $audits[0]->getOldValues()['stockQuantity']);
        self::assertSame(15, $audits[0]->getNewValues()['stockQuantity']);
    }

    public function testNegativeAdjustmentPersistsDelta(): void
    {
        $result = $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                -4,
                'Damaged goods',
                'stock-minus-'.bin2hex(random_bytes(8)),
            ),
        );

        self::assertSame(10, $result->quantityBefore);
        self::assertSame(-4, $result->quantityChange);
        self::assertSame(6, $result->quantityAfter);

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(6, $product->getStockQuantity());
    }

    public function testInsufficientStockDoesNotMutateAndFailsIdempotency(): void
    {
        $key = 'stock-insufficient-'.bin2hex(random_bytes(8));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            sprintf('Insufficient stock for product %d.', $this->productId()),
        );

        try {
            $this->handler()(
                new AdjustStockInput(
                    $this->productId(),
                    -11,
                    'Too much',
                    $key,
                ),
            );
        } finally {
            $this->entityManager->clear();

            $product = $this->entityManager->find(Product::class, $this->productId());
            self::assertNotNull($product);
            self::assertSame(10, $product->getStockQuantity());
            self::assertCount(0, $this->movements());
            self::assertCount(0, $this->audits());

            $record = $this->findIdempotency($key);
            self::assertNotNull($record);
            self::assertSame(IdempotencyStatus::FAILED, $record->getStatus());
        }
    }

    public function testZeroAdjustmentIsRejectedBeforeMutation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock adjustment cannot be zero.');

        $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                0,
                null,
                'stock-zero-'.bin2hex(random_bytes(8)),
            ),
        );
    }

    public function testSameKeySameRequestReplaysWithoutDuplicateSideEffects(): void
    {
        $key = 'stock-replay-'.bin2hex(random_bytes(8));
        $input = new AdjustStockInput(
            $this->productId(),
            5,
            'Restock',
            $key,
        );

        $first = $this->handler()($input);

        $this->entityManager->clear();

        $second = $this->handler()($input);

        self::assertSame($first->productId, $second->productId);
        self::assertSame($first->quantityBefore, $second->quantityBefore);
        self::assertSame($first->quantityAfter, $second->quantityAfter);
        self::assertSame($first->stockMovementId, $second->stockMovementId);

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(15, $product->getStockQuantity());
        self::assertCount(1, $this->movements());
        self::assertCount(1, $this->audits());

        $record = $this->findIdempotency($key);
        self::assertNotNull($record);
        self::assertSame(IdempotencyStatus::COMPLETED, $record->getStatus());
        self::assertSame(200, $record->getResponseStatus());
    }

    public function testSameKeyDifferentRequestIsRejected(): void
    {
        $key = 'stock-conflict-'.bin2hex(random_bytes(8));

        $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                5,
                'Restock',
                $key,
            ),
        );

        $this->entityManager->clear();

        $this->expectException(IdempotencyConflict::class);

        try {
            $this->handler()(
                new AdjustStockInput(
                    $this->productId(),
                    6,
                    'Different request',
                    $key,
                ),
            );
        } finally {
            $this->entityManager->clear();

            $product = $this->entityManager->find(Product::class, $this->productId());
            self::assertNotNull($product);
            self::assertSame(15, $product->getStockQuantity());
            self::assertCount(1, $this->movements());
            self::assertCount(1, $this->audits());
        }
    }

    public function testDifferentKeysApplyIndependently(): void
    {
        $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                5,
                'Restock A',
                'stock-a-'.bin2hex(random_bytes(8)),
            ),
        );

        $this->entityManager->clear();

        $this->handler()(
            new AdjustStockInput(
                $this->productId(),
                -3,
                'Correction B',
                'stock-b-'.bin2hex(random_bytes(8)),
            ),
        );

        $this->entityManager->clear();

        $product = $this->entityManager->find(Product::class, $this->productId());
        self::assertNotNull($product);
        self::assertSame(12, $product->getStockQuantity());
        self::assertCount(2, $this->movements());
        self::assertCount(2, $this->audits());
    }

    public function testUnknownProductIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->handler()(
            new AdjustStockInput(
                999999,
                5,
                'Unknown',
                'stock-not-found-'.bin2hex(random_bytes(8)),
            ),
        );
    }

    private function handler(): \App\Application\Product\Command\AdjustStock\AdjustStockHandler
    {
        return self::getContainer()->get(AdjustStockHandler::class);
    }

    private function productId(): int
    {
        $id = $this->product->getId();
        self::assertNotNull($id);

        return $id;
    }

    /** @return list<StockMovement> */
    private function movements(): array
    {
        return $this->entityManager
            ->getRepository(StockMovement::class)
            ->createQueryBuilder('m')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<AuditLog> */
    private function audits(): array
    {
        return $this->entityManager
            ->getRepository(AuditLog::class)
            ->createQueryBuilder('a')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function findIdempotency(string $key): ?IdempotencyRecord
    {
        return $this->entityManager
            ->getRepository(IdempotencyRecord::class)
            ->createQueryBuilder('i')
            ->where('i.idempotencyKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
