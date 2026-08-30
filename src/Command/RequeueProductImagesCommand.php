<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Product\Message\ProcessProductImage;
use App\Domain\Product\Enum\ImageStatus;
use App\Domain\Product\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:product-images:requeue',
    description: 'Re-dispatch stale uploading product images.',
)]
final class RequeueProductImagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('stale-minutes', InputArgument::OPTIONAL, 'Age before an UPLOADING image is considered stale.', '10')
            ->addArgument('batch-size', InputArgument::OPTIONAL, 'Maximum images per run.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $minutes = filter_var($input->getArgument('stale-minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $batch = filter_var($input->getArgument('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($minutes === false || $batch === false) {
            $output->writeln('<error>stale-minutes and batch-size must be positive integers.</error>');
            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $minutes));
        $images = $this->entityManager->createQueryBuilder()
            ->select('i.id')
            ->from(ProductImage::class, 'i')
            ->andWhere('i.status = :status')
            ->andWhere('COALESCE(i.updatedAt, i.createdAt) < :cutoff')
            ->setParameter('status', ImageStatus::UPLOADING)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults($batch)
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($images as $id) {
            $this->messageBus->dispatch(new ProcessProductImage((int) $id));
        }

        $output->writeln(sprintf('Re-dispatched %d stale product image(s).', count($images)));
        return Command::SUCCESS;
    }
}
