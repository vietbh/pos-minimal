# PHASE UI/UX 9.4 — Payment Reference Prefix Normalization

## Scope

Allow payment-reference inputs/display values to contain a small, explicit set of human-facing prefixes while keeping the persisted POS `PaymentReference` canonical.

### Accepted forms

- `A879403A47A9`
- `PAY-A879403A47A9`
- `PAY_A879403A47A9`
- `PAY A879403A47A9`
- `REF: A879403A47A9`
- `REFERENCE-A879403A47A9`

All normalize to:

`A879403A47A9`

A bare `PAY12345678` is **not** treated as a prefixed value; this avoids silently changing a legitimate canonical reference.

## Integration points

- `PaymentReferenceNormalizer` is the single normalization rule for user/display reference input.
- Manual bank confirmation normalizes the submitted reference before repository lookup.
- Checkout payload validation normalizes optional `payment.paymentReference` input.
- Bank-notification webhook normalization uses the same canonicalizer.
- Bank notification parsing prioritizes an explicit `REF` / `REFERENCE` marker before the generic token fallback. This prevents VCB provider transaction IDs from being mistaken for the POS reference.

## Invariants preserved

- Database `PaymentReference.reference` remains canonical `A-Z0-9`, 8–32 characters.
- Existing payment-reference uniqueness remains unchanged.
- No business amount, order, stock, transaction, idempotency, or concurrency rules are changed.
- Prefix handling is input normalization only; it does not create a new payment reference.
