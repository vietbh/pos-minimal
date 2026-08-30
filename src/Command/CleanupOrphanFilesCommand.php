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
    name: 'app:product-images:cleanup',
    description: 'Remove old files in image storage that have no matching ProductImage storage key.',
)]
final class CleanupOrphanFilesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $storageRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('min-age-hours', InputArgument::OPTIONAL, 'Only delete files older than this many hours.', '24')
            ->addArgument('max-files', InputArgument::OPTIONAL, 'Maximum files to delete in one run.', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = filter_var($input->getArgument('min-age-hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $maxFiles = filter_var($input->getArgument('max-files'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
        if ($hours === false || $maxFiles === false) {
            $output->writeln('<error>min-age-hours and max-files must be positive integers.</error>');
            return Command::INVALID;
        }

        if (!is_dir($this->storageRoot)) {
            $output->writeln('<info>Storage root does not exist; nothing to clean.</info>');
            return Command::SUCCESS;
        }

        $keys = $this->connection->fetchFirstColumn('SELECT storage_key FROM product_images');
        $known = array_fill_keys(array_map(static fn (string $key): string => str_replace('\\', '/', ltrim($key, '/')), $keys), true);
        $cutoff = time() - ($hours * 3600);
        $deleted = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->storageRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $deleted >= $maxFiles) {
                continue;
            }

            $path = $file->getPathname();
            if ($file->getMTime() > $cutoff) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(substr($path, strlen(rtrim($this->storageRoot, DIRECTORY_SEPARATOR))), '/\\'));
            if ($relative === '' || isset($known[$relative])) {
                continue;
            }

            if (!unlink($path)) {
                $output->writeln(sprintf('<error>Unable to delete orphan file: %s</error>', $relative));
                return Command::FAILURE;
            }

            ++$deleted;
        }

        $output->writeln(sprintf('Removed %d orphan storage file(s).', $deleted));
        return Command::SUCCESS;
    }
}
