<?php

declare(strict_types=1);

namespace App\Application\RootAnalytics;

use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Enum\SessionStatus;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;

final readonly class RootAnalyticsService
{
    private const ONLINE_AFTER_SECONDS = 120;
    private const IDLE_AFTER_SECONDS = 900;
    private const ACTION_PAGE_SIZE = 5;

    public function __construct(
        private UserRepositoryInterface $users,
        private UserSessionRepositoryInterface $sessions,
        private AuditLogRepositoryInterface $auditLogs,
    ) {
    }

    /**
     * ROOT-only operational analysis. ROOT itself is excluded from every
     * presentation collection and audit result.
     *
     * @return array<string,mixed>
     */
    public function dashboard(
        ?int $actorUserId = null,
        ?string $action = null,
        ?string $search = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $toExclusive = null,
        int $page = 1,
        ?int $selectedUserId = null,
    ): array {
        $requestStats = [];
        foreach ($this->sessions->requestStatsByUser() as $row) {
            $requestStats[$row['userId']] = $row;
        }

        $latestSessions = [];
        foreach ($this->sessions->findRecentForAnalytics(5000) as $session) {
            $user = $session->getUser();
            $userId = $user->getId();
            if ($userId === null || isset($latestSessions[$userId])) {
                continue;
            }
            $latestSessions[$userId] = $session;
        }

        $latestActiveSessions = [];
        foreach ($this->sessions->findRecentForAnalytics(5000, SessionStatus::ACTIVE) as $session) {
            $user = $session->getUser();
            $userId = $user->getId();
            if ($userId === null || isset($latestActiveSessions[$userId])) {
                continue;
            }
            $latestActiveSessions[$userId] = $session;
        }

        $now = new \DateTimeImmutable();
        $users = [];
        foreach ($this->users->findAllOrderedByUsername() as $user) {
            if ($user->hasRole(UserRole::ROOT)) {
                continue;
            }

            $userId = $user->getId();
            if ($userId === null) {
                continue;
            }

            $stats = $requestStats[$userId] ?? [
                'requestCount' => 0,
                'sessionCount' => 0,
                'activeSessionCount' => 0,
            ];
            $latest = $latestSessions[$userId] ?? null;
            $latestActive = $latestActiveSessions[$userId] ?? null;
            $presence = $user->isActive()
                ? $this->presence($latestActive?->getLastActivityAt(), $now)
                : ['key' => 'offline', 'label' => 'offline'];

            $users[] = [
                'user' => $user,
                'requestCount' => (int) $stats['requestCount'],
                'sessionCount' => (int) $stats['sessionCount'],
                'activeSessionCount' => (int) ($stats['activeSessionCount'] ?? 0),
                'presence' => $presence,
                'lastActivityAt' => $latest?->getLastActivityAt(),
                'lastIpAddress' => $latest?->getIpAddress(),
                'lastDevice' => $latest?->getDevice(),
                'lastRequestMethod' => $latest?->getLastRequestMethod(),
                'lastRequestPath' => $latest?->getLastRequestPath(),
            ];
        }

        $selectedUser = null;
        $selectedSessions = [];
        if ($selectedUserId !== null) {
            $candidate = $this->users->findById($selectedUserId);
            if ($candidate instanceof User && !$candidate->hasRole(UserRole::ROOT)) {
                $selectedUser = $candidate;
                $selectedSessions = $this->sessions->findByUser($candidate, 50);
            }
        }

        $auditPage = $this->auditLogs->findRootAnalyticsPage(
            $actorUserId,
            $action,
            $search,
            $from,
            $toExclusive,
            $page,
            self::ACTION_PAGE_SIZE,
        );

        return [
            'users' => $users,
            'actions' => $auditPage['items'],
            'actionTotal' => $auditPage['total'],
            'actionPage' => max(1, $page),
            'actionPageSize' => self::ACTION_PAGE_SIZE,
            'actionPageCount' => max(1, (int) ceil($auditPage['total'] / self::ACTION_PAGE_SIZE)),
            'actionOptions' => $this->auditLogs->findRootAnalyticsActions(),
            'selectedUser' => $selectedUser,
            'selectedSessions' => $selectedSessions,
            'actionFilters' => [
                'actorUserId' => $actorUserId,
                'action' => $action,
                'search' => $search,
                'from' => $from?->format('Y-m-d'),
                'to' => $toExclusive?->modify('-1 day')->format('Y-m-d'),
            ],
        ];
    }

    /** @return array{key:string,label:string} */
    private function presence(?\DateTimeImmutable $lastActivity, \DateTimeImmutable $now): array
    {
        if ($lastActivity === null) {
            return ['key' => 'offline', 'label' => 'offline'];
        }

        $age = max(0, $now->getTimestamp() - $lastActivity->getTimestamp());
        if ($age < self::ONLINE_AFTER_SECONDS) {
            return ['key' => 'online', 'label' => 'online'];
        }
        if ($age < self::IDLE_AFTER_SECONDS) {
            return ['key' => 'idle', 'label' => 'idle'];
        }

        return ['key' => 'offline', 'label' => 'offline'];
    }
}
