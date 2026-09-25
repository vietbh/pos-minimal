<?php

declare(strict_types=1);

namespace App\Controller\Statistics;

use App\Application\Security\Permission;
use App\Application\Statistics\Query\GetDashboardHandler;
use App\Application\Statistics\Query\Period\StatisticsPeriodResolver;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController extends AbstractController
{
    public function __construct(private readonly StatisticsPeriodResolver $periodResolver) {}

    #[Route('/app/statistics', name: 'statistics_index', methods: ['GET'])]
    public function index(Request $request, GetDashboardHandler $handler, SalesPointRepositoryInterface $salesPoints): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);

        $preset = trim((string) $request->query->get('preset', 'custom'));
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : 10;
        $salesPointId = $request->query->getInt('salesPointId', 0) ?: null;

        try {
            $period = $this->periodResolver->resolve(
                $preset,
                $request->query->get('from'),
                $request->query->get('to'),
            );
            $input = new StatisticsQueryInput($period->from, $period->toExclusive, $limit, $salesPointId);
            $result = $handler($input);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return $this->render('statistics/index.html.twig', [
            'dashboard' => $result,
            'from' => $period->fromValue,
            'to' => $period->toValue,
            'limit' => $limit,
            'preset' => $period->preset,
            'timezone' => $period->from->getTimezone()->getName(),
            'sales_points' => $salesPoints->findActive(),
            'sales_point_id' => $salesPointId,
        ]);
    }
}
