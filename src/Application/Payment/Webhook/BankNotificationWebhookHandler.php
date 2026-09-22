<?php

declare(strict_types=1);

namespace App\Application\Payment\Webhook;

use App\Application\Payment\Reference\PaymentReferenceNormalizer;

final readonly class BankNotificationWebhookHandler
{
    public function __construct(
        private BankNotificationReconciliationService $reconciliation,
        private ?PaymentReferenceNormalizer $referenceNormalizer = null,
        private string $webhookTimezone = 'Asia/Ho_Chi_Minh',
        private int $futureSkewSeconds = 300,
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): array
    {
        // Provider is optional tracking metadata. It is deliberately NOT a
        // business matching condition and is never restricted to MACRODROID.
        $provider = trim((string) ($payload['provider'] ?? ''));
        $provider = $provider === '' ? 'BANK_NOTIFICATION' : strtoupper($provider);
        if (strlen($provider) > 40) {
            throw new \InvalidArgumentException('provider must be <= 40 characters.');
        }

        $externalId = trim((string) (
            $payload['externalTransactionId']
            ?? $payload['eventId']
            ?? $payload['transactionId']
            ?? ''
        ));

        $description = trim((string) (
            $payload['content']
            ?? $payload['notificationText']
            ?? $payload['description']
            ?? $payload['text']
            ?? ''
        ));

        if ($description === '' || strlen($description) > 500) {
            throw new \InvalidArgumentException('content is required and must be <= 500 characters.');
        }

        $reference = $this->extractPaymentReference($description);
        if ($reference === null) {
            throw new \InvalidArgumentException('A valid bank payment reference is required in content.');
        }

        // Some notification clients send a legacy display reference such as
        // PAY-A879403A47A9 while the POS canonical reference is A879403A47A9.
        // The content remains the authoritative extraction source; when the
        // explicit field is present it must normalize to the same value.
        $payloadReference = trim((string) ($payload['reference'] ?? ''));
        if ($payloadReference !== '') {
            $normalizedPayloadReference = $this->normalizeReference($payloadReference);
            if ($normalizedPayloadReference !== $reference) {
                throw new \DomainException('Bank notification reference conflicts with its content.');
            }
        }

        if ($externalId === '') {
            $externalId = 'REF-' . substr(hash('sha256', $reference . '|' . $description), 0, 64);
        }

        if (strlen($externalId) > 120) {
            throw new \InvalidArgumentException('eventId must be <= 120 characters.');
        }

        $amount = null;
        if (array_key_exists('amount', $payload) && $payload['amount'] !== null && $payload['amount'] !== '') {
            $amount = trim((string) $payload['amount']);
            try {
                \App\Domain\Shared\ValueObject\Money::fromDecimal($amount);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('amount is invalid.', 0, $e);
            }
        }

        $bankAccountId = null;
        if (array_key_exists('bankAccountId', $payload) && $payload['bankAccountId'] !== null && $payload['bankAccountId'] !== '') {
            $bankAccountId = filter_var($payload['bankAccountId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($bankAccountId === false) {
                throw new \InvalidArgumentException('bankAccountId is invalid.');
            }
        }

        $occurredAtRaw = trim((string) ($payload['occurredAt'] ?? ''));
        try {
            if ($occurredAtRaw === '') {
                $occurredAt = new \DateTimeImmutable('now', new \DateTimeZone($this->webhookTimezone));
            } else {
                // A timezone-less timestamp from the Android/MoMo notification
                // client is interpreted in the configured webhook timezone.
                $hasExplicitTimezone = preg_match('/(?:Z|[+-]\\d{2}:?\\d{2})$/i', $occurredAtRaw) === 1;
                $occurredAt = $hasExplicitTimezone
                    ? new \DateTimeImmutable($occurredAtRaw)
                    : new \DateTimeImmutable($occurredAtRaw, new \DateTimeZone($this->webhookTimezone));
            }
        } catch (\Exception) {
            throw new \InvalidArgumentException('occurredAt is invalid.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->webhookTimezone));
        if ($occurredAt > $now->modify(sprintf('+%d seconds', $this->futureSkewSeconds))) {
            throw new \InvalidArgumentException('occurredAt cannot be materially in the future.');
        }

        return $this->reconciliation->reconcile(
            provider: $provider,
            externalId: $externalId,
            description: $description,
            occurredAt: $occurredAt,
            reference: $reference,
            amount: $amount,
            bankAccountId: $bankAccountId,
        );
    }

    private function extractPaymentReference(string $description): ?string
    {
        // Provider notifications may contain an opaque provider transaction
        // token before the POS reference. Prefer an explicit REF/REFERENCE
        // marker when present (e.g. VCB: "... REF 0FAB4217A0DA ...").
        if (preg_match('/\bREF(?:ERENCE)?\s*[:#.-]?\s*([A-Z0-9]{8,32})\b/i', $description, $m) === 1) {
            return $this->normalizeReference($m[1]);
        }

        // Fallback for legacy notifications where the POS reference is the
        // only opaque standalone token available.
        if (preg_match('/(?<![A-Z0-9])([A-Z0-9]{8,32})(?![A-Z0-9])/i', $description, $m) !== 1) {
            return null;
        }

        return $this->normalizeReference($m[1]);
    }

    private function normalizeReference(string $reference): string
    {
        return ($this->referenceNormalizer ?? new PaymentReferenceNormalizer())->normalize($reference);
    }
}
