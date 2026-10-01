<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\Dashboard\DashboardNavigationResolver;
use App\Application\Security\Permission;
use App\Application\Statistics\Goal\KpiGoalService;
use App\Application\Statistics\Query\GetDashboardHandler;
use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private readonly string $appTimezone = 'UTC')
    {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        GetDashboardHandler $handler,
        KpiGoalService $kpiGoals,
        DashboardNavigationResolver $navigationResolver,
        Request $request,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        $timezone = new \DateTimeZone($this->appTimezone);
        $today = new \DateTimeImmutable('today', $timezone);

        // The existing statistics query is permission-gated. Do not load
        // aggregate sales/debt/stock data for users without STATISTICS_VIEW.
        $dashboard = null;
        $activeKpiGoals = [];
        if ($this->isGranted(Permission::STATISTICS_VIEW->value)) {
            $dashboard = $handler(new StatisticsQueryInput(
                from: $today,
                toExclusive: $today->modify('+1 day'),
                limit: 5,
            ));

            $now = new \DateTimeImmutable('now', $timezone);
            $todayStart = $now->setTime(0, 0, 0);
            $todayEnd = $todayStart->setTime(23, 59, 59);
            $kpiViews = $kpiGoals->views($now);
            $activeKpiGoals = array_values(array_filter(
                $kpiViews,
                static fn (\App\Application\Statistics\Goal\KpiGoalView $goal): bool =>
                    $goal->startDate <= $todayEnd && $goal->endDate >= $todayStart,
            ));

            // Keep the dashboard useful after a KPI expires: show active goals first,
            // then the most recent goals so the user can still see the result/status.
            if ($activeKpiGoals === [] && $kpiViews !== []) {
                $activeKpiGoals = array_slice($kpiViews, 0, 3);
            }
        }

        $roleLabel = 'Nhân viên';
        if ($user->hasRole(UserRole::ROOT)) {
            $roleLabel = 'Root';
        } elseif ($user->hasRole(UserRole::ADMIN)) {
            $roleLabel = 'Quản trị viên';
        }

        $navigation = $navigationResolver->resolve(
            $user,
            $request->query->get('tab'),
        );

        return $this->render('application/home.html.twig', [
            'user' => $user,
            'dashboard' => $dashboard,
            'active_kpi_goals' => $activeKpiGoals,
            'can_open_pos' => $this->isGranted(Permission::POS_ACCESS->value),
            'role_label' => $roleLabel,
            'is_admin_role' => $user->hasRole(UserRole::ADMIN) || $user->hasRole(UserRole::ROOT),
            'dashboard_navigation' => $navigation,
        ]);
    }
}
