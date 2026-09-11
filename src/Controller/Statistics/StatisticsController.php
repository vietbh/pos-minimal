<?php

declare(strict_types=1);

namespace App\Controller\Statistics;

use App\Application\Security\Permission;
use App\Application\Statistics\Query\GetDashboardHandler;
use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController extends AbstractController
{
    public function __construct(private readonly string $appTimezone = 'UTC') {}

    #[Route('/app/statistics', name: 'statistics_index', methods: ['GET'])]
    public function index(Request $request, GetDashboardHandler $handler): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }
        $this->denyAccessUnlessGranted(Permission::STATISTICS_VIEW->value);

        $timezone = new \DateTimeZone($this->appTimezone);
        $today = new \DateTimeImmutable('today', $timezone);
        $fromValue = trim((string) $request->query->get('from', $today->format('Y-m-d')));
        $toValue = trim((string) $request->query->get('to', $today->format('Y-m-d')));
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : 10;

        try {
            $from = $this->parseDate($fromValue, $timezone);
            $to = $this->parseDate($toValue, $timezone);
            $toExclusive = $to->modify('+1 day');
            $input = new StatisticsQueryInput($from, $toExclusive, $limit);
            $result = $handler($input);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return $this->render('statistics/index.html.twig', [
            'dashboard' => $result,
            'from' => $fromValue,
            'to' => $toValue,
            'limit' => $limit,
            'timezone' => $timezone->getName(),
        ]);
    }

    private function parseDate(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Statistics date is required.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid statistics date.');
        }
        return $date;
    }
}
