<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\Security\Permission;
use App\Application\Statistics\Query\GetDashboardHandler;
use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private readonly string $appTimezone = 'UTC')
    {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(GetDashboardHandler $handler): Response
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
        if ($this->isGranted(Permission::STATISTICS_VIEW->value)) {
            $dashboard = $handler(new StatisticsQueryInput(
                from: $today,
                toExclusive: $today->modify('+1 day'),
                limit: 5,
            ));
        }

        $roleLabel = 'Nhân viên';
        if ($user->hasRole(UserRole::ROOT)) {
            $roleLabel = 'Root';
        } elseif ($user->hasRole(UserRole::ADMIN)) {
            $roleLabel = 'Quản trị viên';
        }

        return $this->render('application/home.html.twig', [
            'user' => $user,
            'dashboard' => $dashboard,
            'can_open_pos' => $this->isGranted(Permission::POS_ACCESS->value),
            'role_label' => $roleLabel,
            'is_admin_role' => $user->hasRole(UserRole::ADMIN) || $user->hasRole(UserRole::ROOT),
        ]);
    }
}
