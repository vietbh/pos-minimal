<?php

declare(strict_types=1);

namespace App\Controller\Root;

use App\Application\RootAnalytics\RootAnalyticsService;
use App\Application\User\UserManagementService;
use App\Application\Security\Permission;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/root/analytics')]
final class RootAnalyticsController extends AbstractController
{
    #[Route('', name: 'root_analytics', methods: ['GET'])]
    public function index(
        Request $request,
        RootAnalyticsService $analytics,
        UserRepositoryInterface $users,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->hasRole(UserRole::ROOT)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(Permission::ROOT_ANALYTICS_VIEW->value);

        $actorId = $this->nullableInt($request->query->get('actor'));
        $action = $this->nullableString($request->query->get('action'));
        $search = $this->nullableString($request->query->get('search'));
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));
        $toExclusive = $to?->modify('+1 day');
        $page = max(1, (int) $request->query->get('page', 1));
        $selectedUserId = $this->nullableInt($request->query->get('user'));

        $dashboard = $analytics->dashboard(
            $actorId,
            $action,
            $search,
            $from,
            $toExclusive,
            $page,
            $selectedUserId,
        );

        $nonRootUsers = array_values(array_filter(
            $users->findAllOrderedByUsername(),
            static fn (User $user): bool => !$user->hasRole(UserRole::ROOT),
        ));

        return $this->render('root/analytics.html.twig', [
            ...$dashboard,
            'filterUsers' => $nonRootUsers,
        ]);
    }

    #[Route('/users/{id<\d+>}/sessions/{sessionId<\d+>}/revoke', name: 'root_analytics_session_revoke', methods: ['POST'])]
    public function revokeSession(
        int $id,
        int $sessionId,
        Request $request,
        UserManagementService $service,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->hasRole(UserRole::ROOT)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(Permission::ROOT_ANALYTICS_VIEW->value);
        if (!$csrf->isTokenValid(new CsrfToken('root_analytics_session', (string) $request->request->get('_token', '')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $service->revokeSession(
                $actor,
                $id,
                $sessionId,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
            );
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('Session not found.');
        }

        return $this->redirectToRoute('root_analytics', ['user' => $id]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !ctype_digit((string) $value)) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}
