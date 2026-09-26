<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Application\Customer\Query\SearchCustomers\SearchCustomersHandler;
use App\Application\Customer\Query\SearchCustomers\SearchCustomersInput;
use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Order\Query\CopyOrderToPos\CopyOrderToPosHandler;
use App\Application\Order\Query\CopyOrderToPos\CopyOrderToPosInput;
use App\Application\Payment\Reference\PaymentReferenceService;
use App\Application\Payment\Reference\PaymentReferenceNormalizer;
use App\Application\Payment\Reference\CheckoutPaymentSessionService;
use App\Application\Payment\ManualBankPaymentConfirmationService;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Application\Order\Command\CompleteOrder\CompleteOrderHandler;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Security\ActorContext;
use App\Application\Security\Permission;
use App\Application\Security\RuntimeActorContextProvider;
use App\Application\SalesPoint\CurrentSalesPoint;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Application\Product\Query\ProductCatalogHandler;
use App\Application\Product\Query\ProductCatalogInput;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
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

    #[Route('/app/pos', name: 'pos', methods: ['GET'])]
    public function pos(Request $request, CsrfTokenManagerInterface $csrfTokenManager, PaymentBankAccountRepositoryInterface $bankAccounts, SalesPointRepositoryInterface $salesPoints, CurrentSalesPoint $currentSalesPoint): Response
    {
        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        if (!$this->isGranted(Permission::POS_ACCESS->value)) {
            throw $this->createAccessDeniedException();
        }

        $current = $currentSalesPoint->get();
        if ($current === null) {
            $current = $salesPoints->findActive()[0] ?? null;
            if ($current !== null) { $currentSalesPoint->set($current); }
        }

        $copyFromOrder = $request->query->getInt('copyFromOrder', 0);
        if ($copyFromOrder <= 0) {
            $copyFromOrder = null;
        }

        return $this->render('pos/index.html.twig', [
            'copy_from_order_id' => $copyFromOrder,
            'checkout_csrf_token' => $csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'payment_bank_accounts' => $bankAccounts->findActive(),
            'manual_bank_confirm_allowed' => $this->isGranted(Permission::PAYMENT_BANK_MANUAL_CONFIRM->value),
            'sales_points' => $salesPoints->findActive(),
            'current_sales_point' => $current,
        ]);
    }

    #[Route('/app/pos/current-sales-point', name: 'pos_current_sales_point', methods: ['GET'], format: 'json')]
    public function currentSalesPoint(CurrentSalesPoint $current, SalesPointRepositoryInterface $salesPoints): JsonResponse
    {
        $this->requirePosAccess(Permission::POS_ACCESS);
        $point = $current->get();
        if ($point === null) {
            $point = $salesPoints->findActive()[0] ?? null;
            if ($point !== null) { $current->set($point); }
        }
        return $this->json(['data' => $point ? ['id'=>$point->getId(),'code'=>$point->getCode(),'name'=>$point->getName(),'type'=>$point->getType()->value] : null]);
    }

    #[Route('/app/pos/current-sales-point', name: 'pos_current_sales_point_set', methods: ['POST'], format: 'json')]
    public function setCurrentSalesPoint(Request $request, CurrentSalesPoint $current, SalesPointRepositoryInterface $salesPoints, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        $this->requirePosAccess(Permission::POS_ACCESS);
        $token=(string)$request->headers->get('X-CSRF-TOKEN','');
        if (!$csrf->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID,$token))) return $this->json(['errorCode'=>'CSRF_INVALID','message'=>'Invalid CSRF token.'],Response::HTTP_FORBIDDEN);
        $id=$request->request->getInt('salesPointId',0);
        $point=$id>0?$salesPoints->findById($id):null;
        if ($point===null || !$point->isActive()) return $this->json(['errorCode'=>'SALES_POINT_UNAVAILABLE','message'=>'Sales point is not available.'],Response::HTTP_UNPROCESSABLE_ENTITY);
        $current->set($point);
        return $this->json(['data'=>['id'=>$point->getId(),'code'=>$point->getCode(),'name'=>$point->getName(),'type'=>$point->getType()->value]]);
    }

    #[Route('/app/pos/copy-from-order/{id<\d+>}', name: 'pos_copy_order_to_pos', methods: ['GET'], format: 'json')]
    public function copyFromOrder(int $id, CopyOrderToPosHandler $handler): JsonResponse
    {
        $this->requirePosAccess(Permission::ORDER_VIEW);

        $result = $handler(new CopyOrderToPosInput($id));
        if ($result === null) {
            return $this->json([
                'errorCode' => 'ORDER_NOT_FOUND',
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => [
                'sourceOrderId' => $result->sourceOrderId,
                'sourceOrderNumber' => $result->sourceOrderNumber,
                'customer' => $result->customerId === null ? null : [
                    'id' => $result->customerId,
                    'name' => $result->customerName,
                    'phone' => $result->customerPhone,
                    'defaultDiscountPercent' => $result->customerDefaultDiscountPercent,
                ],
                'cashTenderedAmount' => $result->cashTenderedAmount,
                'items' => array_map(static fn ($item): array => [
                    'productId' => $item->productId,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'unitPrice' => $item->unitPrice,
                    'quantity' => $item->quantity,
                    'active' => $item->active,
                ], $result->items),
            ],
        ]);
    }

    #[Route('/app/pos/products', name: 'pos_products_search', methods: ['GET'], format: 'json')]
    public function searchProducts(Request $request, SearchProductsHandler $handler): JsonResponse
    {
        $this->requirePosAccess(Permission::PRODUCT_VIEW);
        $query = trim((string) $request->query->get('q', ''));
        $limit = min(20, max(1, $request->query->getInt('limit', 20)));
        $results = $handler(new SearchProductsInput($query, $limit));

        return $this->json([
            'data' => array_map(static fn ($product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'sellingPrice' => $product->sellingPrice,
                'stockQuantity' => $product->stockQuantity,
            ], $results),
        ]);
    }

    #[Route('/app/pos/products/catalog', name: 'pos_products_catalog', methods: ['GET'], format: 'json')]
    public function catalogProducts(
        Request $request,
        ProductCatalogHandler $handler,
        ProductCategoryRepositoryInterface $categories,
    ): JsonResponse {
        $this->requirePosAccess(Permission::PRODUCT_VIEW);

        $query = trim((string) $request->query->get('q', ''));
        $categoryRaw = $request->query->get('category');
        $categoryId = is_numeric($categoryRaw) && (int) $categoryRaw > 0 ? (int) $categoryRaw : null;
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(5, max(1, $request->query->getInt('limit', 5)));

        $result = $handler(new ProductCatalogInput(
            query: $query,
            categoryId: $categoryId,
            sort: 'name_asc',
            page: $page,
            limit: $limit,
        ));

        if ($result->page > $result->totalPages && $result->total > 0) {
            $result = $handler(new ProductCatalogInput(
                query: $query,
                categoryId: $categoryId,
                sort: 'name_asc',
                page: $result->totalPages,
                limit: $limit,
            ));
        }

        return $this->json([
            'data' => array_map(static fn ($product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'sellingPrice' => $product->sellingPrice,
                'stockQuantity' => $product->stockQuantity,
                'categoryId' => $product->categoryId,
                'categoryName' => $product->categoryName,
            ], $result->items),
            'pagination' => [
                'page' => $result->page,
                'limit' => $result->limit,
                'total' => $result->total,
                'totalPages' => $result->totalPages,
            ],
            'categories' => array_map(static fn ($category): array => [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ], $categories->findActiveOrdered()),
        ]);
    }

    #[Route('/app/pos/customers', name: 'pos_customers_search', methods: ['GET'], format: 'json')]
    public function searchCustomers(Request $request, SearchCustomersHandler $handler): JsonResponse
    {
        $this->requirePosAccess(Permission::CUSTOMER_VIEW);
        $query = trim((string) $request->query->get('q', ''));
        $limit = min(20, max(1, $request->query->getInt('limit', 20)));
        $results = $handler(new SearchCustomersInput($query, $limit));

        return $this->json([
            'data' => array_map(static fn ($customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'defaultDiscountPercent' => $customer->defaultDiscountPercent,
            ], $results),
        ]);
    }

    #[Route('/app/pos/customers', name: 'pos_customer_create', methods: ['POST'], format: 'json')]
    public function createCustomer(
        Request $request,
        CreateCustomerHandler $handler,
        CsrfTokenManagerInterface $csrf,
    ): JsonResponse {
        $this->requirePosAccess(Permission::CUSTOMER_CREATE);

        $token = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrf->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            return $this->json([
                'errorCode' => 'CSRF_INVALID',
                'message' => 'Invalid CSRF token.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'errorCode' => 'VALIDATION_ERROR',
                'message' => 'Invalid customer payload.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $id = $handler(new CreateCustomerInput(
                name: trim((string) ($payload['name'] ?? '')),
                phone: isset($payload['phone']) ? trim((string) $payload['phone']) : null,
                note: isset($payload['note']) ? trim((string) $payload['note']) : null,
                defaultDiscountPercent: isset($payload['defaultDiscountPercent']) ? (int) $payload['defaultDiscountPercent'] : 0,
            ));

            return $this->json([
                'data' => [
                    'id' => $id,
                    'name' => trim((string) ($payload['name'] ?? '')),
                    'phone' => isset($payload['phone']) && trim((string) $payload['phone']) !== ''
                        ? trim((string) $payload['phone'])
                        : null,
                    'defaultDiscountPercent' => isset($payload['defaultDiscountPercent']) ? (int) $payload['defaultDiscountPercent'] : 0,
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException | \DomainException $e) {
            return $this->json([
                'errorCode' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/app/pos/payment-bank-accounts', name: 'pos_payment_bank_accounts', methods: ['GET'], format: 'json')]
    public function paymentBankAccounts(PaymentBankAccountRepositoryInterface $bankAccounts): JsonResponse
    {
        $this->requirePosAccess(Permission::POS_ACCESS);
        return $this->json(['data' => array_map(static fn ($account): array => [
            'id' => $account->getId(),
            'bankBin' => $account->getBankBin(),
            'bankName' => $account->getBankName(),
            'accountNumber' => $account->getAccountNumber(),
            'accountName' => $account->getAccountName(),
            'qrTemplate' => $account->getQrTemplate(),
            'transferContentTemplate' => $account->getTransferContentTemplate(),
        ], $bankAccounts->findActive())]);
    }

    #[Route('/app/payment-sessions/{id<\d+>}/status', name: 'checkout_payment_session_status', methods: ['GET'], format: 'json')]
    public function paymentSessionStatus(int $id, CheckoutPaymentSessionRepositoryInterface $sessions, PaymentReferenceRepositoryInterface $references, ExternalPaymentTransactionRepositoryInterface $transactions): JsonResponse
    {
        $this->requirePosAccess(Permission::POS_CHECKOUT);
        $session = $sessions->findById($id);
        if ($session === null) {
            return $this->json(['errorCode' => 'RESOURCE_NOT_FOUND', 'message' => 'Payment session not found.'], Response::HTTP_NOT_FOUND);
        }
        $reference = null;
        $pendingReference = $references->findLatestPendingBySession($id);
        if ($pendingReference !== null && !$pendingReference->isExpired()) {
            $reference = $pendingReference->getReference();
        }
        foreach ($session->getOrder()?->getPayments() ?? [] as $payment) {
            if ($payment->getMethod() === PaymentMethod::BANK_TRANSFER) {
                $reference = $payment->getReference();
                break;
            }
        }
        $order = $session->getOrder();
        $externalTransaction = $order?->getId() !== null
            ? $transactions->findLatestByOrderId($order->getId())
            : null;

        return $this->json(['data' => [
            'sessionId' => $session->getId(),
            'status' => $session->getStatus()->value,
            'orderId' => $order?->getId(),
            'orderNumber' => $order?->getOrderNumber()?->value(),
            'total' => $session->getAmount()->toDecimal(),
            'paidAmount' => $session->getOrder()?->getPaidAmount()->toDecimal() ?? '0.00',
            'debtAmount' => $session->getOrder()?->getDebtAmount()->toDecimal() ?? $session->getAmount()->toDecimal(),
            'paymentReceived' => $session->getStatus()->value === 'PAID',
            'paymentReference' => $reference,
            'manualConfirmationAvailable' => $this->isGranted(Permission::PAYMENT_BANK_MANUAL_CONFIRM->value)
                && $session->getStatus()->value === 'WAITING_FOR_BANK_PAYMENT'
                && $reference !== null,
            'externalTransaction' => $externalTransaction === null ? null : [
                'id' => $externalTransaction->getId(),
                'provider' => $externalTransaction->getProvider(),
                'externalTransactionId' => $externalTransaction->getExternalTransactionId(),
                'amount' => $externalTransaction->getAmount()->toDecimal(),
                'description' => $externalTransaction->getDescription(),
                'reference' => $externalTransaction->getTransactionReference(),
                'occurredAt' => $externalTransaction->getOccurredAt()->format(\DateTimeInterface::ATOM),
                'status' => $externalTransaction->getStatus(),
            ],
        ]]);
    }

    #[Route('/app/payment-sessions/{id<\\d+>}/manual-confirm', name: 'checkout_payment_session_manual_confirm', methods: ['POST'], format: 'json')]
    public function manualBankPaymentConfirm(
        int $id,
        Request $request,
        ManualBankPaymentConfirmationService $service,
        CsrfTokenManagerInterface $csrfTokenManager,
        PaymentReferenceNormalizer $referenceNormalizer,
    ): JsonResponse {
        $requestId = $this->requestId($request);
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return $this->error('AUTHENTICATION_REQUIRED', 'Authentication is required.', Response::HTTP_UNAUTHORIZED, $requestId);
        }
        if (!$this->isGranted(Permission::PAYMENT_BANK_MANUAL_CONFIRM->value)) {
            return $this->error('ACCESS_DENIED', 'You are not allowed to manually confirm bank payments.', Response::HTTP_FORBIDDEN, $requestId);
        }
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrf))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $requestId);
        }
        try {
            $payload = $request->toArray();
            $reference = trim((string) ($payload['paymentReference'] ?? ''));
            $amount = trim((string) ($payload['amount'] ?? ''));
            if ($reference === '' || $amount === '') {
                throw new \InvalidArgumentException('paymentReference and amount are required.');
            }
            $reference = $referenceNormalizer->normalize($reference);
            $result = $service->confirmAndComplete($id, $reference, $amount, $user, $requestId);
            return $this->json(['data' => $result, 'requestId' => $requestId], Response::HTTP_OK, ['X-Request-ID' => $requestId]);
        } catch (\InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_BAD_REQUEST, $requestId);
        } catch (\DomainException $e) {
            return $this->error('MANUAL_BANK_CONFIRMATION_REJECTED', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
        } catch (\Throwable) {
            return $this->error('INTERNAL_ERROR', 'Unable to manually confirm bank payment.', Response::HTTP_INTERNAL_SERVER_ERROR, $requestId);
        }
    }

    #[Route('/app/payment-sessions/{id<\\d+>}/payment-reference/regenerate', name: 'payment_session_payment_reference_regenerate', methods: ['POST'], format: 'json')]
    public function regeneratePaymentSessionReference(
        int $id,
        CheckoutPaymentSessionService $service,
        TransactionManagerInterface $transactionManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        Request $request,
    ): JsonResponse {
        $this->requirePosAccess(Permission::POS_CHECKOUT);
        $provided = (string) $request->headers->get('X-CSRF-TOKEN', '');
        $requestId = $this->requestId($request);
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $provided))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $requestId);
        }

        try {
            $result = $transactionManager->run(fn (): array => $service->regenerate($id));
            return $this->json(['data' => $result, 'requestId' => $requestId], Response::HTTP_OK, ['X-Request-ID' => $requestId]);
        } catch (\DomainException $e) {
            return $this->error('PAYMENT_REFERENCE_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $requestId);
        } catch (\Throwable) {
            return $this->error('INTERNAL_ERROR', 'Unable to regenerate payment reference.', Response::HTTP_INTERNAL_SERVER_ERROR, $requestId);
        }
    }

    #[Route('/app/orders/{id<\\d+>}/payment-reference/regenerate', name: 'payment_reference_regenerate', methods: ['POST'], format: 'json')]
    public function regeneratePaymentReference(
        int $id,
        PaymentReferenceService $service,
        TransactionManagerInterface $transactionManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        Request $request,
    ): JsonResponse {
        $this->requirePosAccess(Permission::POS_CHECKOUT);
        $provided = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $provided))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $this->requestId($request));
        }

        try {
            $result = $transactionManager->run(fn (): array => $service->regenerateForOrder($id));
            return $this->json(['data' => $result], Response::HTTP_OK);
        } catch (\DomainException $e) {
            return $this->error('PAYMENT_REFERENCE_INVALID', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $this->requestId($request));
        } catch (\Throwable) {
            return $this->error('INTERNAL_ERROR', 'Unable to regenerate payment reference.', Response::HTTP_INTERNAL_SERVER_ERROR, $this->requestId($request));
        }
    }

    #[Route('/app/orders/{id<\d+>}/complete', name: 'order_complete', methods: ['POST'], format: 'json')]
    public function completeOrder(
        int $id,
        Request $request,
        CompleteOrderHandler $handler,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $requestId = $this->requestId($request);
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return $this->error('AUTHENTICATION_REQUIRED', 'Authentication is required.', Response::HTTP_UNAUTHORIZED, $requestId);
        }

        if (!$this->isGranted(Permission::POS_CHECKOUT->value)) {
            return $this->error('ACCESS_DENIED', 'You are not allowed to complete a sale.', Response::HTTP_FORBIDDEN, $requestId);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))) {
            return $this->error('CSRF_INVALID', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN, $requestId);
        }

        try {
            $result = $handler->handle($id, $user, $requestId);
        } catch (\Throwable $exception) {
            return $this->mapException($exception, $requestId);
        }

        return $this->json([
            'data' => [
                'orderId' => $result->orderId,
                'orderNumber' => $result->orderNumber,
                'status' => $result->status,
                'total' => $result->total,
                'paidAmount' => $result->paidAmount,
                'debtAmount' => $result->debtAmount,
            ],
            'requestId' => $requestId,
        ], Response::HTTP_OK, ['X-Request-ID' => $requestId]);
    }

    #[Route('/app/checkout', name: 'pos_checkout', methods: ['POST'], format: 'json')]
    public function checkout(
        Request $request,
        CheckoutHandlerEntryPoint $handler,
        RuntimeActorContextProvider $actorContextProvider,
        CsrfTokenManagerInterface $csrfTokenManager,
        PaymentReferenceNormalizer $referenceNormalizer,
        CurrentSalesPoint $currentSalesPoint,
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
            $current = $currentSalesPoint->get();
            if ($current === null) { throw new \InvalidArgumentException('Select a sales point before checkout.'); }
            $payload['salesPointId'] = $current->getId();
            $input = $this->toInput($payload, $idempotencyKey, $referenceNormalizer);
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
                'tenderedAmount' => $result->tenderedAmount->toDecimal(),
                'changeAmount' => $result->changeAmount->toDecimal(),
                'status' => $result->status?->value,
                'paymentReference' => $result->paymentReference,
                'paymentReferenceExpiresAt' => $result->paymentReferenceExpiresAt,
                'paymentReferenceTransferContent' => $result->paymentReferenceTransferContent,
                'paymentReferenceQrUrl' => $result->paymentReferenceQrUrl,
                'bankTransferCompletionPolicy' => $result->bankTransferCompletionPolicy,
                'paymentSessionId' => $result->paymentSessionId,
            ],
            'requestId' => $requestId,
        ], Response::HTTP_OK, [
            'X-Request-ID' => $requestId,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function toInput(array $payload, string $idempotencyKey, PaymentReferenceNormalizer $referenceNormalizer): CheckoutInput
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
        $tenderedAmount = $payment['tenderedAmount'] ?? null;
        $bankAccountId = $payment['bankAccountId'] ?? null;
        $paymentReference = $payment['paymentReference'] ?? null;

        if (!is_string($method) || (!is_string($amount) && !is_int($amount))) {
            throw new \InvalidArgumentException('payment.method and payment.amount are required.');
        }

        if ($bankAccountId !== null && !is_int($bankAccountId)) {
            throw new \InvalidArgumentException('payment.bankAccountId must be an integer or null.');
        }

        if ($paymentReference !== null && !is_string($paymentReference)) {
            throw new \InvalidArgumentException('payment.paymentReference must be a string or null.');
        }

        $paymentReference = $paymentReference !== null
            ? $referenceNormalizer->normalize($paymentReference)
            : null;

        if ($tenderedAmount !== null && !is_string($tenderedAmount) && !is_int($tenderedAmount)) {
            throw new \InvalidArgumentException('payment.tenderedAmount must be a string, integer, or null.');
        }

        $paymentMethod = PaymentMethod::tryFrom($method);
        if ($paymentMethod === null) {
            throw new \InvalidArgumentException('Unsupported payment method.');
        }

        $customerId = $payload['customerId'] ?? null;
        if ($customerId !== null && !is_int($customerId)) {
            throw new \InvalidArgumentException('customerId must be an integer or null.');
        }

        $salesPointId = $payload['salesPointId'] ?? null;
        if ($salesPointId !== null && !is_int($salesPointId)) { throw new \InvalidArgumentException('salesPointId must be an integer or null.'); }

        $note = $payload['note'] ?? null;
        if ($note !== null && !is_string($note)) {
            throw new \InvalidArgumentException('note must be a string or null.');
        }

        $manualDiscount = $payload['manualDiscount'] ?? null;
        if ($manualDiscount !== null && !is_string($manualDiscount) && !is_int($manualDiscount)) {
            throw new \InvalidArgumentException('manualDiscount must be a string, integer, or null.');
        }

        try {
            $money = Money::fromDecimal((string) $amount);
            $tenderedMoney = $tenderedAmount === null
                ? null
                : Money::fromDecimal((string) $tenderedAmount);
            $manualDiscountMoney = $manualDiscount === null
                ? null
                : Money::fromDecimal((string) $manualDiscount);
            if ($manualDiscountMoney !== null && $manualDiscountMoney->minorUnits() < 0) {
                throw new \InvalidArgumentException('manualDiscount cannot be negative.');
            }
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        return new CheckoutInput(
            items: $mappedItems,
            customerId: $customerId,
            payment: new CheckoutPaymentInput($paymentMethod, $money, $tenderedMoney),
            bankAccountId: $bankAccountId,
            paymentReference: $paymentReference,
            note: $note,
            idempotencyKey: $idempotencyKey,
            salesPointId: $salesPointId,
            manualDiscount: $manualDiscountMoney,
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

    private function requirePosAccess(Permission $permission): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        if (!$this->isGranted(Permission::POS_ACCESS->value) || !$this->isGranted($permission->value)) {
            throw $this->createAccessDeniedException('Access denied.');
        }
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
