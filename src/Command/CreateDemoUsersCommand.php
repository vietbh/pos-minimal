<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-demo-users',
    description: 'Create development/test demo users for the POS.',
)]
final class CreateDemoUsersCommand extends Command
{
    private const ADMIN_USERNAME = 'admin';
    private const ADMIN_PASSWORD = 'admin123';
    private const USER_USERNAME = 'user';
    private const USER_PASSWORD = 'user123';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            $output->writeln('<error>This command is available only in dev/test environments.</error>');
            return Command::FAILURE;
        }

        $created = 0;
        $created += $this->createIfMissing(self::ADMIN_USERNAME, self::ADMIN_PASSWORD, [UserRole::ADMIN], $output);
        $created += $this->createIfMissing(self::USER_USERNAME, self::USER_PASSWORD, [UserRole::USER], $output);

        if ($created > 0) {
            $this->entityManager->flush();
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Demo users ready. Created: %d.</info>', $created));

        return Command::SUCCESS;
    }

    /** @param list<UserRole> $roles */
    private function createIfMissing(
        string $username,
        string $plainPassword,
        array $roles,
        OutputInterface $output,
    ): int {
        if ($this->userRepository->findByUsername($username) !== null) {
            $output->writeln(sprintf('<comment>%s → already exists</comment>', $username));
            return 0;
        }

        $user = new User($username);
        $user->setRoles(array_map(
            static fn (UserRole $role): string => $role->value,
            $roles,
        ));
        $user->setPasswordHash(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );

        $this->userRepository->save($user);
        $output->writeln(sprintf('<info>%s → created</info>', $username));

        return 1;
    }
}
