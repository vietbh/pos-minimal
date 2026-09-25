<?php
declare(strict_types=1);

namespace App\Controller\Payment;

use App\Application\Payment\Webhook\BankNotificationWebhookHandler;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BankNotificationWebhookController extends AbstractController
{
    public function __construct() {}

    #[Route('/webhooks/bank-notification', name: 'webhook_bank_notification', methods: ['POST'], format: 'json')]
    public function __invoke(Request $request, BankNotificationWebhookHandler $handler, PaymentBankAccountRepositoryInterface $accounts): JsonResponse
    {
        $provided = (string)$request->headers->get('X-Webhook-Token', '');

        try {
            $payload = $request->toArray();
            $bankAccountId = filter_var($payload['bankAccountId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($bankAccountId === false) {
                return $this->json(['errorCode'=>'BANK_ACCOUNT_REQUIRED','message'=>'bankAccountId is required.'], Response::HTTP_BAD_REQUEST);
            }
            $account = $accounts->findById((int)$bankAccountId);
            if ($account === null || $provided === '' || !hash_equals($account->getWebhookToken(), $provided)) {
                return $this->json([
                    'errorCode' => 'WEBHOOK_UNAUTHORIZED',
                    'message' => 'Invalid webhook token.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $result = $handler->handle($payload);

            return $this->json([
                'ok' => true,
                'data' => $result,
            ], $result['status'] === 'DUPLICATE' ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
        } catch (\JsonException|\InvalidArgumentException $e) {
            return $this->json([
                'errorCode' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            $message = $e->getMessage();
            $errorCode = match (true) {
                str_contains($message, 'Payment reference not found') => 'REFERENCE_NOT_FOUND',
                str_contains($message, 'expired') => 'REFERENCE_EXPIRED',
                str_contains($message, 'no longer valid') => 'PAYMENT_CONFLICT',
                str_contains($message, 'outstanding amount') => 'PAYMENT_CONFLICT',
                str_contains($message, 'Order is no longer') => 'PAYMENT_CONFLICT',
                str_contains($message, 'conflicts with existing external transaction') => 'PAYMENT_CONFLICT',
                default => 'VALIDATION_ERROR',
            };

            $status = $errorCode === 'PAYMENT_CONFLICT'
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->json([
                'errorCode' => $errorCode,
                'message' => $message,
            ], $status);
        } catch (\Throwable $e) {
            return $this->json([
                'errorCode' => 'INTERNAL_ERROR',
                'message' => 'Unable to process bank notification. '. $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
