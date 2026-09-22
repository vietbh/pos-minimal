<?php

declare(strict_types=1);

namespace App\Application\Payment\Reference;

/**
 * Converts human-entered/display payment references to the canonical
 * PaymentReference value stored by the POS.
 *
 * Canonical references are uppercase A-Z/0-9 only. A small, explicit set of
 * display prefixes is accepted so callers can safely submit values such as
 * PAY-ABC12345 or REF: ABC12345 without changing the stored reference.
 */
final class PaymentReferenceNormalizer
{
    public function normalize(string $reference): string
    {
        $reference = strtoupper(trim($reference));

        $reference = preg_replace(
            '/^(?:PAY|REF|REFERENCE)[\\s:._-]+/i',
            '',
            $reference,
        ) ?? $reference;

        if (preg_match('/^[A-Z0-9]{8,32}$/', $reference) !== 1) {
            throw new \InvalidArgumentException('Payment reference has an invalid format.');
        }

        return $reference;
    }
}
