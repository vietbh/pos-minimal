<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Application\Order\Command\CancelOrder\CancelOrderHandlerEntryPoint;
use App\Application\Order\Command\CancelOrder\CancelOrderInput;
use App\Application\Order\Command\RefundOrder\RefundOrderHandlerEntryPoint;
use App\Application\Order\Command\RefundOrder\RefundOrderInput;
use App\Application\Security\ActorContext;
use App\Application\Security\Permission;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class OrderLifecycleController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'order_lifecycle';

    #[Route('/app/orders/{id<\d+>}/cancel', name: 'order_cancel', methods: ['POST'], format: 'json')]
    public function cancel(
        int $id,
        Request $request,
        CancelOrderHandlerEntryPoint $handler,
        RuntimeActorContextProvider $actorContextProvider,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        return $this->mutate(
            $id,
            $request,
            Permission::ORDER_CANCEL,
            self::CSRF_TOKEN_ID,
            $csrfTokenManager,
            $actorContextProvider,
            function (string $reason, string $key) use ($handler, $id) {
                return $handler->handle(new CancelOrderInput($id, $reason, $key));
            },
            'order_cancel',
            'ORDER_CANCEL',
        );
    }

    #[Route('/app/orders/{id<\d+>}/refund', name: 'order_refund', methods: ['POST'], format: 'json')]
    public function refund(
        int $id,
        Request $request,
        RefundOrderHandlerEntryPoint $handler,
        RuntimeActorContextProvider $actorContextProvider,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        return $this->mutate(
            $id,
            $request,
            Permission::ORDER_REFUND,
            self::CSRF_TOKEN_ID,
            $csrfTokenManager,
            $actorContextProvider,
            function (string $reason, string $key) use ($handler, $id) {
                return $handler->handle(new RefundOrderInput($id, $reason, $key));
            },
            'order_refund',
            'ORDER_REFUND',
        );
    }

    private function mutate(
        int $id,
        Request $request,
        Permission $permission,
        string $csrfTokenId,
        CsrfTokenManagerInterface $csrfTokenManager,
        RuntimeActorContextProvider $actorContextProvider,
        callable $handler,
        string $operation,
        string $auditAction,
    ): JsonResponse {
        $requestId = $this->requestId($request);
        $user = $this->getUser();

        if (!$user instanceof User || $user->getId() === null || !$user->isActive()) {
            return $this->error('AUTHENTICATION_REQUIRED', 'Authentication is required.', Response::HTTP_UNAUTHORIZED, $requestId);
        }
        if (!$this->isGranted($permission->value)) {
            return $this->error('ACCESS_DENIED', 'You are not allowed to perform this order operation.', Response::HTTP_FORBIDDEN, $requestId);
        }
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken($csrfTokenId, $csrf))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $requestId);
        }
        $key = trim((string) $request->headers->get('Idempotency-Key', ''));
        if ($key === '') {
            return $this->error('IDEMPOTENCY_KEY_REQUIRED', 'Idempotency-Key header is required.', Response::HTTP_BAD_REQUEST, $requestId);
        }

        try {
            $payload = $request->toArray();
            $reason = $payload['reason'] ?? null;
            if (!is_string($reason) || trim($reason) === '') {
                throw new \InvalidArgumentException('reason is required.');
            }
            $reason = trim($reason);
            if (mb_strlen($reason) > 255) {
                throw new \InvalidArgumentException('reason cannot exceed 255 characters.');
            }
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->error('VALIDATION_ERROR', $exception->getMessage(), Response::HTTP_BAD_REQUEST, $requestId);
        }

        $actorContextProvider->set(new ActorContext($user->getId(), null, $requestId));
        try {
            $result = $handler($reason, $key);
        } catch (\Throwable $exception) {
            return $this->mapException($exception, $requestId, $operation);
        } finally {
            $actorContextProvider->clear();
        }

        $data = [
            'orderId' => $result->orderId,
            'orderNumber' => $result->orderNumber,
            'status' => $result->status->value,
            'restoredQuantity' => $result->restoredQuantity,
        ];
        $data[$auditAction === 'ORDER_CANCEL' ? 'reversedAmount' : 'refundedAmount'] =
            ($auditAction === 'ORDER_CANCEL' ? $result->reversedAmount : $result->refundedAmount)->toDecimal();

        return $this->json(['data' => $data, 'requestId' => $requestId], Response::HTTP_OK, ['X-Request-ID' => $requestId]);
    }

    private function mapException(\Throwable $exception, string $requestId, string $operation): JsonResponse
    {
        $message = $exception->getMessage();
        if ($exception instanceof \App\Application\Common\Idempotency\IdempotencyConflict) {
            return $this->error('IDEMPOTENCY_CONFLICT', 'Idempotency key conflicts with an existing request.', 409, $requestId);
        }
        if ($exception instanceof \InvalidArgumentException) {
            return $this->error('VALIDATION_ERROR', $message, 400, $requestId);
        }
        if ($exception instanceof \RuntimeException && $message === 'Order not found.') {
            return $this->error('ORDER_NOT_FOUND', 'Order was not found.', 404, $requestId);
        }
        if ($exception instanceof \DomainException) {
            if (str_contains($message, 'already in progress')) {
                return $this->error('IDEMPOTENCY_IN_PROGRESS', 'This request is already being processed.', 409, $requestId);
            }
            if (str_contains($message, 'already been cancelled')) {
                return $this->error('ORDER_ALREADY_CANCELLED', $message, 409, $requestId);
            }
            if (str_contains($message, 'already been refunded')) {
                return $this->error('ORDER_ALREADY_REFUNDED', $message, 409, $requestId);
            }
            if (str_contains($message, 'Only completed orders can be')) {
                return $this->error('ORDER_INVALID_STATE', $message, 409, $requestId);
            }
            if (str_contains($message, 'financial reversal has already')) {
                return $this->error('REFUND_ALREADY_PROCESSED', $message, 409, $requestId);
            }
            return $this->error('BUSINESS_RULE_VIOLATION', $message, 422, $requestId);
        }
        return $this->error('INTERNAL_ERROR', 'Unable to complete order operation.', 500, $requestId);
    }

    private function requestId(Request $request): string
    {
        $candidate = trim((string) $request->headers->get('X-Request-ID', ''));
        return $candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $candidate) === 1
            ? $candidate
            : bin2hex(random_bytes(16));
    }

    private function error(string $code, string $message, int $status, string $requestId): JsonResponse
    {
        return $this->json(['errorCode' => $code, 'message' => $message, 'requestId' => $requestId], $status, ['X-Request-ID' => $requestId]);
    }
}
