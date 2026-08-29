<?php

declare(strict_types=1);

namespace App\Infrastructure\Transaction;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(TransactionContextInterface): T $operation
     *
     * @return T
     * @throws Exception
     */
    public function run(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();

        $connection->beginTransaction();

        try {
            $context = new DoctrineTransactionContext(
                $this->entityManager,
            );

            $result = $operation($context);

            $this->entityManager->flush();

            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->entityManager->clear();

            throw $exception;
        }
    }
}
