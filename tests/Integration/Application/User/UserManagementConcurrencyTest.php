<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\User;

use App\Domain\Audit\AuditLog;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pcntl')]
final class UserManagementConcurrencyTest extends IntegrationTestCase
{
    public function testConcurrentRoleUpdatesSerializeWithoutCorruptingUserState(): void
    {
        $actorA = new User('user-race-admin-a');
        $actorA->grantRole(UserRole::ADMIN);
        $actorB = new User('user-race-admin-b');
        $actorB->grantRole(UserRole::ADMIN);
        $target = new User('concurrent-target');

        $this->entityManager->persist($actorA);
        $this->entityManager->persist($actorB);
        $this->entityManager->persist($target);
        $this->entityManager->flush();

        $results = $this->runWorkers(
            actorIds: [$this->idOf($actorA), $this->idOf($actorB)],
            targetId: $this->idOf($target),
            roles: [UserRole::ADMIN->value, UserRole::USER->value],
        );

        $this->entityManager->clear();
        $persisted = $this->entityManager->find(User::class, $this->idOf($target));
        self::assertInstanceOf(User::class, $persisted);
        self::assertContains($persisted->getRoles()[0] ?? null, [UserRole::USER->value, UserRole::ADMIN->value]);

        $audits = $this->entityManager->getRepository(AuditLog::class)->findBy([
            'entityType' => 'User',
            'entityId' => (string) $this->idOf($target),
            'action' => 'USER_UPDATED',
        ]);
        self::assertCount(2, $audits);
        self::assertCount(2, array_filter($results, static fn (array $r): bool => ($r['status'] ?? null) === 'success'));
    }

    /** @param list<int> $actorIds @param list<string> $roles @return list<array<string,mixed>> */
    private function runWorkers(array $actorIds, int $targetId, array $roles): array
    {
        $dir = sys_get_temp_dir().'/mobile-pos-user-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0700, true));
        $start = $dir.'/start';
        $processes = [];
        $resultFiles = [];

        try {
            foreach ([0, 1] as $index) {
                $result = $dir.'/result-'.$index.'.json';
                $resultFiles[] = $result;
                $descriptor = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
                $process = proc_open([
                    PHP_BINARY,
                    dirname(__DIR__, 3).'/Support/UserManagementConcurrencyWorker.php',
                    (string) $actorIds[$index],
                    (string) $targetId,
                    $roles[$index],
                    $start,
                    $result,
                ], $descriptor, $pipes, dirname(__DIR__, 4));
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes[1], $pipes[2]];
            }

            $deadline = microtime(true) + 30;
            foreach ($resultFiles as $result) {
                while (!file_exists($result.'.ready')) {
                    if (microtime(true) >= $deadline) {
                        self::fail('User management concurrency workers did not become ready.');
                    }
                    usleep(1000);
                }
            }
            touch($start);

            foreach ($processes as [$process, $stdout, $stderr]) {
                stream_get_contents($stdout);
                $error = trim(stream_get_contents($stderr));
                fclose($stdout);
                fclose($stderr);
                $exit = proc_close($process);
                self::assertSame(0, $exit, $error);
            }

            $results = [];
            foreach ($resultFiles as $result) {
                $deadline = microtime(true) + 30;
                while (!file_exists($result)) {
                    if (microtime(true) >= $deadline) {
                        self::fail('User management concurrency worker did not produce a result.');
                    }
                    usleep(1000);
                }
                $decoded = json_decode((string) file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);
                $results[] = $decoded;
            }
            return $results;
        } finally {
            foreach ($resultFiles as $result) { @unlink($result); @unlink($result.'.ready'); }
            @unlink($start);
            @rmdir($dir);
        }
    }

    private function idOf(object $entity): int
    {
        $id = $entity->getId();
        self::assertNotNull($id);
        return $id;
    }
}
