<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Statistics\MercurePublisher;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:statistics:publish-notifications', description: 'Publish committed statistics invalidations to Mercure.')]
final class PublishStatisticsNotificationsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MercurePublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->publisher->isEnabled()) {
            $output->writeln('<comment>Mercure is disabled: configure MERCURE_PUBLISH_URL and MERCURE_JWT_SECRET.</comment>');
            return Command::SUCCESS;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, event_id, scope_id, topic, attempts FROM statistics_notification_outbox WHERE published_at IS NULL ORDER BY id ASC LIMIT 100'
        );
        $published = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                $this->publisher->publish((string) $row['topic'], (string) $row['event_id'], (int) $row['scope_id']);
                $this->connection->update('statistics_notification_outbox', [
                    'published_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'attempts' => (int) $row['attempts'] + 1,
                    'last_error' => null,
                ], ['id' => (int) $row['id']]);
                ++$published;
            } catch (\Throwable $e) {
                $this->connection->update('statistics_notification_outbox', [
                    'attempts' => (int) $row['attempts'] + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ], ['id' => (int) $row['id']]);
                $this->logger->warning('Mercure statistics notification failed and will be retried.', [
                    'event_id' => $row['event_id'], 'scope_id' => $row['scope_id'], 'exception' => $e,
                ]);
                ++$failed;
            }
        }

        $output->writeln(sprintf('Published %d notification(s); %d failed and remain pending.', $published, $failed));
        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
