<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Application\Order\Query\GetOrder\GetOrderHandler;
use App\Application\Order\Query\GetOrder\GetOrderInput;
use App\Application\Order\Query\ListOrders\ListOrdersHandler;
use App\Application\Order\Query\ListOrders\ListOrdersInput;
use App\Application\Security\Permission;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    private const MAX_SEARCH_LENGTH = 100;
    private const MAX_DATE_RANGE_DAYS = 366;
    private const ALLOWED_LIST_QUERY = ['q', 'status', 'from', 'to', 'page', 'perPage'];

    #[Route('/app/orders', name: 'orders_index', methods: ['GET'])]
    public function index(Request $request, ListOrdersHandler $handler): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        $this->denyAccessUnlessGranted(Permission::ORDER_VIEW->value);

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('perPage', 20)));
        $search = trim((string) $request->query->get('q', ''));
        if (preg_match('/^.{101,}$/us', $search) === 1) {
            throw $this->createBadRequestException('Search query is too long.');
        }

        $statusValue = trim((string) $request->query->get('status', ''));
        $status = $statusValue === '' ? null : OrderStatus::tryFrom($statusValue);

        if ($statusValue !== '' && $status === null) {
            throw $this->createBadRequestException('Unknown order status.');
        }

        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));

        if ($from !== null && $to !== null && $from->diff($to)->days > self::MAX_DATE_RANGE_DAYS) {
            throw $this->createBadRequestException('Order date range cannot exceed 366 days.');
        }

        $result = $handler(new ListOrdersInput(
            search: $search,
            status: $status,
            from: $from,
            to: $to,
            page: $page,
            perPage: $perPage,
        ));

        return $this->render('order/index.html.twig', [
            'orders' => $result,
            'search' => $search,
            'status' => $status,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'statuses' => OrderStatus::cases(),
            'statusLabels' => [
                OrderStatus::DRAFT->value => 'Nháp',
                OrderStatus::COMPLETED->value => 'Hoàn tất',
                OrderStatus::CANCELLED->value => 'Đã hủy',
                OrderStatus::REFUNDED->value => 'Đã hoàn tiền',
            ],
            'maxSearchLength' => self::MAX_SEARCH_LENGTH,
        ]);
    }

    #[Route('/app/orders/{id<\\d+>}/payment-status', name: 'order_payment_status', methods: ['GET'], format: 'json')]
    public function paymentStatus(int $id, GetOrderHandler $handler): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->json(['errorCode' => 'AUTHENTICATION_REQUIRED', 'message' => 'Authentication is required.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->denyAccessUnlessGranted(Permission::POS_CHECKOUT->value);

        $order = $handler(new GetOrderInput($id));
        if ($order === null) {
            return $this->json(['errorCode' => 'RESOURCE_NOT_FOUND', 'message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => [
                'orderId' => $order->id,
                'orderNumber' => $order->orderNumber,
                'status' => $order->status->value,
                'paidAmount' => $order->paidAmount,
                'debtAmount' => $order->debtAmount,
                'paymentReceived' => $order->status->value !== 'CANCELLED' && $order->debtAmount === '0.00' && $order->paidAmount === $order->total,
            ],
        ]);
    }

    #[Route('/app/orders/{id<\\d+>}', name: 'orders_show', methods: ['GET'])]
    public function show(Request $request, int $id, GetOrderHandler $handler): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        $this->denyAccessUnlessGranted(Permission::ORDER_VIEW->value);

        $order = $handler(new GetOrderInput($id));
        if ($order === null) {
            throw $this->createNotFoundException('Order not found.');
        }

        $backQuery = [];
        foreach (self::ALLOWED_LIST_QUERY as $key) {
            $value = $request->query->get($key);
            if ($value !== null && $value !== '') {
                $backQuery[$key] = $value;
            }
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
            'backQuery' => $backQuery,
        ]);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw $this->createBadRequestException('Invalid date filter.');
        }

        return $date;
    }
}
