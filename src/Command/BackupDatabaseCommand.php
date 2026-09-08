<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:backup:database',
    description: 'Create a consistent MySQL database backup using mysqldump.',
)]
final class BackupDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $backupRoot,
        private readonly string $mysqldumpBinary,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('label', InputArgument::OPTIONAL, 'Optional backup label.', 'manual');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $label = (string) $input->getArgument('label');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $label)) {
            $output->writeln('<error>Backup label may contain only letters, numbers, dot, underscore and dash.</error>');
            return Command::INVALID;
        }

        $params = $this->connection->getParams();
        $database = $params['dbname'] ?? null;
        $user = $params['user'] ?? null;
        $password = $params['password'] ?? null;
        $host = $params['host'] ?? '127.0.0.1';
        $port = $params['port'] ?? null;

        if (!is_string($database) || $database === '' || !is_string($user) || $user === '') {
            $output->writeln('<error>Database connection parameters are incomplete.</error>');
            return Command::FAILURE;
        }

        if (!is_dir($this->backupRoot) && !mkdir($concurrentDirectory = $this->backupRoot, 0750, true) && !is_dir($concurrentDirectory)) {
            $output->writeln('<error>Unable to create backup directory.</error>');
            return Command::FAILURE;
        }

        if (!is_writable($this->backupRoot)) {
            $output->writeln('<error>Backup directory is not writable.</error>');
            return Command::FAILURE;
        }

        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');
        $path = rtrim($this->backupRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$database.'_'.$label.'_'.$timestamp.'.sql';

        $command = [
            $this->mysqldumpBinary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--skip-lock-tables',
            '--host='.$host,
            '--user='.$user,
            '--result-file='.$path,
        ];
        if ($port !== null) {
            $command[] = '--port='.(string) $port;
        }
        $command[] = $database;

        $output->writeln(sprintf('Creating database backup: %s', $path));
        $process = new Process($command);
        if (is_string($password) && $password !== '') {
            $process->setEnv(['MYSQL_PWD' => $password]);
        }
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($path);
            $output->writeln('<error>mysqldump failed.</error>');
            if ($process->getErrorOutput() !== '') {
                $output->writeln('<error>'.$process->getErrorOutput().'</error>');
            }
            return Command::FAILURE;
        }

        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            $output->writeln('<error>Backup command succeeded but produced an empty file.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Backup completed successfully.</info>');
        return Command::SUCCESS;
    }
}
