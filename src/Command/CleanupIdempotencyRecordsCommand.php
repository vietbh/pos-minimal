<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:idempotency:cleanup',
    description: 'Remove old terminal idempotency records in bounded batches.',
)]
final class CleanupIdempotencyRecordsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('retention-days', InputArgument::OPTIONAL, 'Terminal record retention in days.', '30')
            ->addArgument('batch-size', InputArgument::OPTIONAL, 'Maximum rows per batch.', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = filter_var($input->getArgument('retention-days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $batch = filter_var($input->getArgument('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
        if ($days === false || $batch === false) {
            $output->writeln('<error>retention-days and batch-size must be positive integers.</error>');
            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
        $total = 0;

        do {
            $deleted = $this->connection->executeStatement(
                'DELETE FROM idempotency_records
                 WHERE status IN (:completed, :failed)
                   AND created_at < :cutoff
                 LIMIT '.$batch,
                [
                    'completed' => 'COMPLETED',
                    'failed' => 'FAILED',
                    'cutoff' => $cutoff->format('Y-m-d H:i:s'),
                ],
            );
            $total += $deleted;
        } while ($deleted === $batch);

        $output->writeln(sprintf('Removed %d terminal idempotency records older than %d days.', $total, $days));
        return Command::SUCCESS;
    }
}
