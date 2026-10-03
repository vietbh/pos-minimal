<?php

declare(strict_types=1);

namespace App\Controller\Statistics;

use App\Application\Security\Permission;
use App\Application\Statistics\Query\GetDashboardHandler;
use App\Application\Statistics\Query\StatisticsQueryRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Application\Statistics\Query\Period\StatisticsPeriodResolver;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Application\Statistics\Goal\KpiGoalService;
use App\Domain\Kpi\KpiGoal;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Statistics\MercureSubscriberTokenFactory;
use Symfony\Component\HttpFoundation\Cookie;

final class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsPeriodResolver $periodResolver,
        private readonly LoggerInterface $logger,
        private readonly ?MercureSubscriberTokenFactory $mercureTokens = null,
        private readonly string $mercurePublicUrl = '',
        private readonly string $appPublicUrl = 'http://localhost',
        private readonly bool $mercureEnabled = false,
    ) {}

    #[Route('/app/statistics', name: 'statistics_index', methods: ['GET'])]
    public function index(Request $request, GetDashboardHandler $handler, SalesPointRepositoryInterface $salesPoints, KpiGoalService $goals): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);

        $preset = trim((string) $request->query->get('preset', 'custom'));
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : 10;
        $salesPointId = $this->nullableQueryInt($request, 'salesPointId');
        $createdGoalId = $request->query->getInt('created_goal_id', 0) ?: null;

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

        $now = new \DateTimeImmutable('now', $period->from->getTimezone());
        $kpiGoals = $goals->views($now, $salesPointId);
        if ($createdGoalId !== null) {
            $createdGoal = $goals->viewById($createdGoalId, $now, $salesPointId);
            if ($createdGoal !== null) {
                $existingIds = array_map(static fn ($goal): int => $goal->id, $kpiGoals);
                if (!in_array($createdGoal->id, $existingIds, true)) {
                    array_unshift($kpiGoals, $createdGoal);
                }
            }
        }

        $response = $this->render('statistics/index.html.twig', [
            'dashboard' => $result,
            'active_tab' => in_array((string) $request->query->get('tab', 'overview'), ['overview', 'sales', 'products', 'payments', 'customers', 'debts'], true) ? (string) $request->query->get('tab', 'overview') : 'overview',
            'from' => $period->fromValue,
            'to' => $period->toValue,
            'limit' => $limit,
            'preset' => $period->preset,
            'timezone' => $period->from->getTimezone()->getName(),
            'sales_points' => $salesPoints->findActive(),
            'sales_point_id' => $salesPointId,
            'kpi_goals' => $kpiGoals,
            'mercure_enabled' => $this->mercureEnabled,
            'mercure_public_url' => $this->mercureEnabled ? $this->mercurePublicUrl : '',
            'mercure_topic' => rtrim($this->appPublicUrl, '/') . '/statistics/' . ($salesPointId ?? 0),
        ]);
        $subscriberToken = $this->mercureEnabled ? $this->mercureTokens?->createForScope($salesPointId ?? 0) : null;
        if ($subscriberToken !== null) {
            $response->headers->setCookie(Cookie::create('mercureAuthorization')
                ->withValue($subscriberToken)
                ->withPath('/.well-known/mercure')
                ->withExpires(new \DateTimeImmutable('+1 hour'))
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX));
        }
        if ($createdGoalId !== null) {
            $response->setPrivate();
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return $response;
    }

    #[Route('/app/statistics/data/{tab}', name: 'statistics_tab_data', requirements: ['tab' => 'sales|products|payments|customers|debts'], methods: ['GET'])]
    public function tabData(string $tab, Request $request, StatisticsQueryRepositoryInterface $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);
        if (!$this->getUser() instanceof User) {
            return new JsonResponse(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $limit = max(1, min(100, $request->query->getInt('limit', 10)));
        $salesPointId = $this->nullableQueryInt($request, 'salesPointId');
        try {
            $period = $this->periodResolver->resolve(
                trim((string) $request->query->get('preset', 'custom')),
                $request->query->get('from'),
                $request->query->get('to'),
            );
            $input = new StatisticsQueryInput($period->from, $period->toExclusive, $limit, $salesPointId);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $rows = match ($tab) {
            'sales' => (function () use ($repository, $input): array {
                $s = $repository->getSalesSummary($input);
                return [
                    ['label' => 'Doanh thu thuần', 'value' => $s->netSales],
                    ['label' => 'Tổng doanh thu', 'value' => $s->grossSales],
                    ['label' => 'Đã thu', 'value' => $s->paymentsCollected],
                    ['label' => 'Đơn hợp lệ', 'value' => $s->qualifyingOrders],
                    ['label' => 'Giá trị đơn trung bình', 'value' => $s->averageOrderValue],
                    ['label' => 'Đã hủy', 'value' => $s->cancelledAmount],
                    ['label' => 'Đã hoàn', 'value' => $s->refundedAmount],
                ];
            })(),
            'products' => (function () use ($repository, $input): array {
                $rows = array_map(static fn ($p): array => ['label' => $p->name, 'value' => $p->quantity . ' sản phẩm · ' . $p->salesAmount], $repository->getTopProducts($input));
                $rows[] = ['label' => 'Số lượng đã bán', 'value' => $repository->getUnitsSold($input)];
                return $rows;
            })(),
            'payments' => array_map(static function ($p): array {
                $method = strtoupper(trim($p->paymentMethod));
                $label = match ($method) {
                    'BANK_TRANSFER', 'BANKTRANSFER' => 'Chuyển khoản ngân hàng',
                    'CASH', 'CASH_PAYMENT' => 'Tiền mặt',
                    default => $p->paymentMethod,
                };

                return ['label' => $label, 'value' => $p->amount . ' · ' . $p->paymentCount . ' giao dịch'];
            }, $repository->getPaymentBreakdown($input)),
            'customers' => array_map(static fn ($c): array => ['label' => $c->name, 'value' => $c->orderCount . ' đơn · ' . $c->totalSpent], $repository->getTopCustomers($input)),
            'debts' => (function () use ($repository, $input, $salesPointId): array {
                $d = $repository->getDebtSummary($input);
                return [
                    ['label' => 'Số khoản nợ', 'value' => $d->debtCount],
                    ['label' => 'Ban đầu', 'value' => $d->originalAmount],
                    ['label' => 'Đã thu', 'value' => $d->collectedAmount],
                    ['label' => 'Còn lại trong kỳ', 'value' => $d->outstandingAmount],
                    ['label' => 'Công nợ còn lại hiện tại', 'value' => $repository->getCurrentOutstandingDebt($salesPointId)],
                ];
            })(),
        };

        return new JsonResponse(['tab' => $tab, 'period' => ['from' => $period->fromValue, 'to' => $period->toValue], 'data' => ['rows' => $rows]]);
    }

    #[Route('/app/statistics/goals', name: 'statistics_goal_create', methods: ['POST'])]
    public function createGoal(
        Request $request,
        KpiGoalService $goals,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }
        if (!$csrf->isTokenValid(new CsrfToken('statistics_goal', (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $metric = trim((string) $request->request->get('metric'));
            $direction = trim((string) $request->request->get('direction'));
            // The KPI target is formatted for humans in the browser (e.g. 100,000).
            // Never depend on the JS formatter for persistence: normalize the submitted
            // value server-side as well so a plain/Turbo/mobile submission cannot fail.
            $target = preg_replace('/[\s\x{00A0}\x{202F},]/u', '', trim((string) $request->request->get('target'))) ?? '';
            $start = new \DateTimeImmutable((string) $request->request->get('start_date') . ' 00:00:00');
            $end = new \DateTimeImmutable((string) $request->request->get('end_date') . ' 00:00:00');
            $salesPointRaw = trim((string) $request->request->get('salesPointId', ''));
            $salesPointId = null;
            if ($salesPointRaw !== '') {
                if (!ctype_digit($salesPointRaw) || (int) $salesPointRaw < 1) {
                    throw new \InvalidArgumentException('Sales point không hợp lệ.');
                }
                $salesPointId = (int) $salesPointRaw;
            }
            $createdGoal = $goals->create($user, $metric, $direction, $target, $start, $end, $salesPointId);
            $this->addFlash('success', 'Đã tạo mục tiêu KPI.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create KPI goal.', [
                'exception' => $e,
                'metric' => $request->request->get('metric'),
                'direction' => $request->request->get('direction'),
                'target' => $request->request->get('target'),
                'start_date' => $request->request->get('start_date'),
                'end_date' => $request->request->get('end_date'),
                'sales_point_id' => $request->request->get('salesPointId'),
            ]);

            $this->addFlash(
                'error',
                'Không thể tạo mục tiêu KPI. Vui lòng thử lại.',
            );
        }

        $redirectQuery = $request->query->all();
        if (isset($createdGoal) && $createdGoal->getId() !== null) {
            $redirectQuery['created_goal_id'] = $createdGoal->getId();
        }

        return $this->redirectToRoute('statistics_index', $redirectQuery);
    }

    #[Route('/app/statistics/goals/{id}/delete', name: 'statistics_goal_delete', methods: ['POST'])]
    public function deleteGoal(int $id, Request $request, KpiGoalService $goals, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);
        if (!$csrf->isTokenValid(new CsrfToken('statistics_goal_delete_' . $id, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $goals->delete($id);
        $this->addFlash('success', 'Đã xóa mục tiêu KPI.');
        return $this->redirectToRoute('statistics_index', $request->query->all());
    }


    /** Treat an omitted, empty, or "all" sales-point filter as no filter. */
    private function nullableQueryInt(Request $request, string $name): ?int
    {
        $query = $request->query->all();
        $value = $query[$name] ?? null;

        // The UI deliberately sends "all" when no sales point is selected.
        // Treat omitted, empty, and "all" consistently as an unscoped query.
        if ($value === null || $value === '' || (is_string($value) && strtolower(trim($value)) === 'all')) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new BadRequestHttpException(sprintf('Query parameter "%s" must be a scalar integer or "all".', $name));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $parsed = filter_var($raw, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            throw new BadRequestHttpException(sprintf('Query parameter "%s" must be a positive integer or "all".', $name));
        }

        return $parsed;
    }

}
