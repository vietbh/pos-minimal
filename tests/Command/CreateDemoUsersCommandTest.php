<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateDemoUsersCommand;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateDemoUsersCommandTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreatesDemoUsers(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $kernel = $this->createMock(KernelInterface::class);

        $kernel->method('getEnvironment')->willReturn('dev');
        $repository->method('findByUsername')->willReturn(null);
        $hasher->method('hashPassword')->willReturn('safe-hash');

        $saved = [];
        $repository->expects(self::exactly(2))->method('save')
            ->willReturnCallback(static function (User $user) use (&$saved): void {
                $saved[$user->getUsername()] = $user;
            });
        $em->expects(self::once())->method('flush');

        $tester = new CommandTester(new CreateDemoUsersCommand($repository, $hasher, $em, $kernel));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertArrayHasKey('admin', $saved);
        self::assertArrayHasKey('user', $saved);
        self::assertTrue($saved['admin']->hasRole(UserRole::ADMIN));
        self::assertFalse($saved['user']->hasRole(UserRole::ADMIN));
        self::assertSame('safe-hash', $saved['admin']->getPassword());
        self::assertSame('safe-hash', $saved['user']->getPassword());
    }

    /**
     * @throws Exception
     */
    public function testIsIdempotent(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $kernel = $this->createMock(KernelInterface::class);

        $kernel->method('getEnvironment')->willReturn('dev');
        $repository->expects(self::exactly(2))->method('findByUsername')->willReturnMap([
            ['admin', new User('admin')],
            ['user', new User('user')],
        ]);
        $repository->expects(self::never())->method('save');
        $hasher->expects(self::never())->method('hashPassword');
        $em->expects(self::never())->method('flush');

        $tester = new CommandTester(new CreateDemoUsersCommand($repository, $hasher, $em, $kernel));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Created: 0', $tester->getDisplay());
    }

    /**
     * @throws Exception
     */
    public function testIsBlockedOutsideDevAndTest(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $kernel = $this->createMock(KernelInterface::class);

        $kernel->method('getEnvironment')->willReturn('prod');
        $repository->expects(self::never())->method('findByUsername');

        $tester = new CommandTester(new CreateDemoUsersCommand($repository, $hasher, $em, $kernel));

        self::assertSame(Command::FAILURE, $tester->execute([]));
    }
}
