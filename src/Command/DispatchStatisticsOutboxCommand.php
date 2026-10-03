<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Statistics\Message\RebuildStatisticsForOrder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:statistics:dispatch-outbox', description: 'Dispatch durable statistics outbox events to Messenger.')]
final class DispatchStatisticsOutboxCommand extends Command
{
    public function __construct(private readonly Connection $connection, private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        // Lock rows while dispatching. The Messenger Doctrine transport uses the same
        // connection in the default setup; if a broker is used, duplicate dispatch is
        // still safe because the projection handler is idempotent.
        $this->connection->beginTransaction();
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, event_id, event_type, aggregate_id FROM statistics_outbox WHERE dispatched_at IS NULL ORDER BY id ASC LIMIT 100 FOR UPDATE SKIP LOCKED'
            );
            foreach ($rows as $row) {
                $this->bus->dispatch(new RebuildStatisticsForOrder(
                    (string) $row['event_id'],
                    (string) $row['event_type'],
                    (int) $row['aggregate_id'],
                ));
                $this->connection->update('statistics_outbox', [
                    'dispatched_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'attempts' => (int) $this->connection->fetchOne('SELECT attempts FROM statistics_outbox WHERE id = ?', [(int) $row['id']]) + 1,
                ], ['id' => (int) $row['id']]);
                ++$count;
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $e;
        }

        $output->writeln(sprintf('Dispatched %d statistics event(s).', $count));
        return Command::SUCCESS;
    }
}
