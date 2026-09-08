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
        $statusValue = trim((string) $request->query->get('status', ''));
        $status = $statusValue === '' ? null : OrderStatus::tryFrom($statusValue);

        if ($statusValue !== '' && $status === null) {
            throw $this->createNotFoundException('Unknown order status.');
        }

        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));

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
        ]);
    }

    #[Route('/app/orders/{id<\\d+>}', name: 'orders_show', methods: ['GET'])]
    public function show(int $id, GetOrderHandler $handler): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        $this->denyAccessUnlessGranted(Permission::ORDER_VIEW->value);

        $order = $handler(new GetOrderInput($id));
        if ($order === null) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
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
            throw $this->createNotFoundException('Invalid date filter.');
        }

        return $date;
    }
}
