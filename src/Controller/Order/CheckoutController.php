<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Security\ActorContext;
use App\Application\Security\Permission;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CheckoutController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'pos_checkout';

    #[Route('/pos', name: 'pos', methods: ['GET'])]
    public function pos(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        if (!$this->isGranted(Permission::POS_ACCESS->value)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('pos/index.html.twig', [
            'checkout_csrf_token' => $csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]);
    }

    #[Route('/api/pos/checkout', name: 'pos_checkout', methods: ['POST'], format: 'json')]
    public function checkout(
        Request $request,
        CheckoutHandlerEntryPoint $handler,
        RuntimeActorContextProvider $actorContextProvider,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $requestId = $this->requestId($request);

        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null || !$user->isActive()) {
            return $this->error('AUTHENTICATION_REQUIRED', 'Authentication is required.', Response::HTTP_UNAUTHORIZED, $requestId);
        }

        if (!$this->isGranted(Permission::POS_CHECKOUT->value)) {
            return $this->error('ACCESS_DENIED', 'You are not allowed to checkout.', Response::HTTP_FORBIDDEN, $requestId);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $requestId);
        }

        $idempotencyKey = trim((string) $request->headers->get('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return $this->error('IDEMPOTENCY_KEY_REQUIRED', 'Idempotency-Key header is required.', Response::HTTP_BAD_REQUEST, $requestId);
        }

        try {
            $payload = $request->toArray();
            $input = $this->toInput($payload, $idempotencyKey);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->error('VALIDATION_ERROR', $exception->getMessage(), Response::HTTP_BAD_REQUEST, $requestId);
        }

        $actorContextProvider->set(new ActorContext(
            userId: $user->getId(),
            sessionId: null,
            requestId: $requestId,
        ));

        try {
            $result = $handler->handle($input);
        } catch (\Throwable $exception) {
            return $this->mapException($exception, $requestId);
        } finally {
            $actorContextProvider->clear();
        }

        return $this->json([
            'data' => [
                'orderId' => $result->orderId,
                'orderNumber' => $result->orderNumber,
                'total' => $result->total->toDecimal(),
                'paidAmount' => $result->paidAmount->toDecimal(),
                'debtAmount' => $result->debtAmount->toDecimal(),
                'status' => $result->status->value,
            ],
            'requestId' => $requestId,
        ], Response::HTTP_OK, [
            'X-Request-ID' => $requestId,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function toInput(array $payload, string $idempotencyKey): CheckoutInput
    {
        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new \InvalidArgumentException('items must contain at least one item.');
        }

        $mappedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each checkout item must be an object.');
            }

            $productId = $item['productId'] ?? null;
            $quantity = $item['quantity'] ?? null;
            if (!is_int($productId) || !is_int($quantity)) {
                throw new \InvalidArgumentException('productId and quantity must be integers.');
            }

            $mappedItems[] = new CheckoutItemInput($productId, $quantity);
        }

        $payment = $payload['payment'] ?? null;
        if (!is_array($payment)) {
            throw new \InvalidArgumentException('payment is required.');
        }

        $method = $payment['method'] ?? null;
        $amount = $payment['amount'] ?? null;
        if (!is_string($method) || (!is_string($amount) && !is_int($amount))) {
            throw new \InvalidArgumentException('payment.method and payment.amount are required.');
        }

        $paymentMethod = PaymentMethod::tryFrom($method);
        if ($paymentMethod === null) {
            throw new \InvalidArgumentException('Unsupported payment method.');
        }

        $customerId = $payload['customerId'] ?? null;
        if ($customerId !== null && !is_int($customerId)) {
            throw new \InvalidArgumentException('customerId must be an integer or null.');
        }

        $note = $payload['note'] ?? null;
        if ($note !== null && !is_string($note)) {
            throw new \InvalidArgumentException('note must be a string or null.');
        }

        try {
            $money = Money::fromDecimal((string) $amount);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        return new CheckoutInput(
            items: $mappedItems,
            customerId: $customerId,
            payment: new CheckoutPaymentInput($paymentMethod, $money),
            note: $note,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function mapException(\Throwable $exception, string $requestId): JsonResponse
    {
        $message = $exception->getMessage();

        if ($exception instanceof \App\Application\Common\Idempotency\IdempotencyConflict) {
            return $this->error('IDEMPOTENCY_CONFLICT', $message, Response::HTTP_CONFLICT, $requestId);
        }

        if ($exception instanceof \InvalidArgumentException) {
            return $this->error('VALIDATION_ERROR', $message, Response::HTTP_BAD_REQUEST, $requestId);
        }

        if ($exception instanceof \DomainException) {
            if (str_contains($message, 'already in progress')) {
                return $this->error('IDEMPOTENCY_IN_PROGRESS', 'Checkout with this idempotency key is already in progress.', Response::HTTP_CONFLICT, $requestId);
            }

            if (str_contains($message, 'Insufficient stock')) {
                return $this->error('INSUFFICIENT_STOCK', $message, Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
            }

            if (str_contains($message, 'inactive')) {
                return $this->error('PRODUCT_INACTIVE', $message, Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
            }

            if (str_contains($message, 'not found')) {
                return $this->error('RESOURCE_NOT_FOUND', $message, Response::HTTP_NOT_FOUND, $requestId);
            }

            if (str_contains($message, 'payment')) {
                return $this->error('INVALID_PAYMENT', $message, Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
            }

            return $this->error('BUSINESS_RULE_VIOLATION', $message, Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
        }

        return $this->error('INTERNAL_ERROR', 'Unable to complete checkout.', Response::HTTP_INTERNAL_SERVER_ERROR, $requestId);
    }

    private function requestId(Request $request): string
    {
        $candidate = trim((string) $request->headers->get('X-Request-ID', ''));
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $candidate) === 1) {
            return $candidate;
        }

        return bin2hex(random_bytes(16));
    }

    /** @param array<string, mixed> $extra */
    private function error(string $errorCode, string $message, int $status, string $requestId): JsonResponse
    {
        return $this->json([
            'errorCode' => $errorCode,
            'message' => $message,
            'requestId' => $requestId,
        ], $status, [
            'X-Request-ID' => $requestId,
        ]);
    }
}
