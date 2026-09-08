<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:production:check',
    description: 'Run non-destructive production readiness checks.',
)]
final class ProductionReadinessCheckCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $storageRoot,
        private readonly string $environment,
        private readonly bool $debug,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;

        if ($this->environment !== 'prod') {
            $output->writeln(sprintf('<comment>Environment is "%s"; production-only checks are informational.</comment>', $this->environment));
        } else {
            $output->writeln('<info>Environment: prod</info>');
        }

        if ($this->debug) {
            $output->writeln('<error>APP_DEBUG is enabled.</error>');
            $failed = true;
        } else {
            $output->writeln('<info>APP_DEBUG: disabled</info>');
        }

        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
            $output->writeln('<info>Database: OK</info>');
        } catch (\Throwable $exception) {
            $output->writeln('<error>Database: FAILED ('.$exception::class.')</error>');
            $failed = true;
        }

        if (!is_dir($this->storageRoot)) {
            $output->writeln('<error>Storage: directory does not exist</error>');
            $failed = true;
        } elseif (!is_writable($this->storageRoot)) {
            $output->writeln('<error>Storage: directory is not writable</error>');
            $failed = true;
        } else {
            $output->writeln('<info>Storage: writable</info>');
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
