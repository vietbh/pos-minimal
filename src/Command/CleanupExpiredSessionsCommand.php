<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Enum\SessionStatus;
use App\Domain\User\UserSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:session:cleanup',
    description: 'Expire inactive user sessions in bounded batches.',
)]
final class CleanupExpiredSessionsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('idle-hours', InputArgument::OPTIONAL, 'Hours of inactivity before expiry.', '24')
            ->addArgument('batch-size', InputArgument::OPTIONAL, 'Maximum sessions per batch.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = filter_var($input->getArgument('idle-hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $batch = filter_var($input->getArgument('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2000]]);
        if ($hours === false || $batch === false) {
            $output->writeln('<error>idle-hours and batch-size must be positive integers.</error>');
            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));
        $total = 0;

        do {
            $sessions = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(UserSession::class, 's')
                ->andWhere('s.status = :status')
                ->andWhere('s.lastActivityAt < :cutoff')
                ->setParameter('status', SessionStatus::ACTIVE)
                ->setParameter('cutoff', $cutoff)
                ->orderBy('s.id', 'ASC')
                ->setMaxResults($batch)
                ->getQuery()
                ->getResult();

            foreach ($sessions as $session) {
                $session->expire();
                ++$total;
            }

            if ($sessions !== []) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        } while (count($sessions) === $batch);

        $output->writeln(sprintf('Expired %d inactive sessions.', $total));
        return Command::SUCCESS;
    }
}
