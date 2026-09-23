# PHASE 10.6 — Payment / Bank Transfer UI

## Scope

Payment and bank-transfer UI integration only. Backend/domain/application/payment state remains authoritative.

## Implemented

- Accessible payment-method fieldset and contextual help.
- Accessible bank-transfer receiving-account details.
- Live payment-reference countdown semantics.
- Translation-backed payment status/help strings; no new user-facing hard-coded Vietnamese in the payment controller.
- Receiving-account admin form labels and user-facing strings are translation-backed.
- Mobile-friendly payment surfaces continue to use existing 48px/52px touch targets and reduced-motion foundation.
- Payment UI exposes state only; it does not calculate or persist payment/order/stock business state beyond existing presentation validation.
- Existing CSRF, idempotency, permission, payment-session, reference-regeneration and manual-confirmation backend contracts are preserved.

## Validation

- EN/VI YAML parse successfully.
- Payment contract test added.
- JavaScript source mirrors the existing public compiled controller artifact.
- No Domain/Application business logic was changed.
