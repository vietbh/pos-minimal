<?php

declare(strict_types=1);

namespace App\Infrastructure\Transaction;

use App\Application\Common\Transaction\TransactionContextInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactionContext implements TransactionContextInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
