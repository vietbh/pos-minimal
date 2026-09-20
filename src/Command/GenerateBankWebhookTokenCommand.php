<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:webhook:generate-token',
    description: 'Generate a secret token for the MacroDroid bank notification webhook.',
)]
final class GenerateBankWebhookTokenCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'write-env-local',
            null,
            InputOption::VALUE_NONE,
            'Write/update BANK_NOTIFICATION_WEBHOOK_TOKEN in .env.local.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $line = 'BANK_NOTIFICATION_WEBHOOK_TOKEN='.$token;

        $output->writeln('<info>Generated webhook token:</info>');
        $output->writeln($line);

        if ($input->getOption('write-env-local')) {
            $envFile = dirname(__DIR__, 2).'/.env.local';
            $existing = is_file($envFile) ? file_get_contents($envFile) : '';
            if ($existing === false) {
                $output->writeln('<error>Unable to read .env.local.</error>');
                return Command::FAILURE;
            }

            if (preg_match('/^BANK_NOTIFICATION_WEBHOOK_TOKEN=.*$/m', $existing) === 1) {
                $existing = preg_replace(
                    '/^BANK_NOTIFICATION_WEBHOOK_TOKEN=.*$/m',
                    $line,
                    $existing,
                ) ?? $existing;
            } else {
                $existing = rtrim($existing, "\r\n")."\n\n".$line."\n";
            }

            if (file_put_contents($envFile, $existing, LOCK_EX) === false) {
                $output->writeln('<error>Unable to write .env.local.</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>Saved to .env.local.</info>');
        } else {
            $output->writeln('<comment>Set this value in .env.local or your production secret store. Do not commit it.</comment>');
        }

        return Command::SUCCESS;
    }
}
