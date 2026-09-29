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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:bootstrap-root',
    description: 'Create or reconcile the protected master ROOT account from runtime secrets.',
)]
final class BootstrapRootAccountCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $rootUsername,
        private readonly string $rootPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim($this->rootUsername);
        $password = $this->rootPassword;

        if ($username === '' || $password === '') {
            $output->writeln('<error>ROOT_ACCOUNT_USERNAME and ROOT_ACCOUNT_PASSWORD must be provided through runtime secrets.</error>');
            return Command::INVALID;
        }

        if (strlen($password) < 12) {
            $output->writeln('<error>ROOT_ACCOUNT_PASSWORD must contain at least 12 characters.</error>');
            return Command::INVALID;
        }

        $existingRoot = $this->userRepository->findRoot();
        $configuredUser = $this->userRepository->findByUsername($username);

        if ($existingRoot instanceof User && $existingRoot->getUsername() !== $username) {
            $output->writeln('<error>A protected ROOT account already exists. Refusing to create a second ROOT account.</error>');
            return Command::FAILURE;
        }

        if ($configuredUser instanceof User && !$configuredUser->hasRole(UserRole::ROOT)) {
            $output->writeln('<error>The configured ROOT username belongs to a non-ROOT account.</error>');
            return Command::FAILURE;
        }

        $root = $existingRoot ?? $configuredUser;
        if (!$root instanceof User) {
            $root = new User($username);
            $this->entityManager->persist($root);
        }

        $root->setRoles([UserRole::ROOT->value]);
        if (!$root->isActive()) {
            $root->activate();
        }

        if (!$root->hasPassword() || !$this->passwordHasher->isPasswordValid($root, $password)) {
            $root->setPasswordHash($this->passwordHasher->hashPassword($root, $password));
        }

        $this->entityManager->flush();

        // Deliberately do not print the username or password. The command's
        // output is safe to retain in CI/deployment logs.
        $output->writeln('<info>Protected ROOT account is ready.</info>');
        return Command::SUCCESS;
    }
}
