# PHASE UI/UX 9.1 — Payment Received Speaker

## Scope

Add an optional browser-side payment notification speaker to the existing POS payment-received flow.

## Behavior

- Cashier explicitly enables payment sound with a visible button.
- Browser uses the Web Speech API (`speechSynthesis`) only for presentation.
- When the authoritative payment-session status reaches `PAID` / `paymentReceived=true`, POS announces the received amount once for that payment event.
- Repeated polling must not repeat the same announcement.
- Unsupported browsers keep the payment UI functional and disable the speaker control.
- Disconnection cancels queued speech.

## Security / business invariants

- Browser JavaScript does not read VCB/bank notifications.
- Browser JavaScript does not decide whether money was received.
- Backend/Symfony remains the payment authority.
- The spoken amount comes from the authoritative payment status payload already used by the POS UI.
- No payment endpoint, transaction boundary, idempotency behavior, concurrency behavior, webhook behavior, or order-completion rule is changed.
- The speaker is presentation-only and must never be used as proof of payment.

## UX

- Mobile-first control with a minimum 48px touch target.
- Clear enabled/disabled state.
- No autoplay attempt on page load; user interaction enables sound.
- Accessible `aria-pressed` and live status.
- Uses the browser locale when available, with Vietnamese/English fallback.

## Verification

Run in the project environment:

```bash
php bin/phpunit
```

Also manually verify on a real browser/device:

1. Open POS.
2. Tap **Enable payment sound**.
3. Browser announces that sound is enabled.
4. Start a bank-transfer checkout.
5. Let the real webhook reconcile the payment.
6. POS shows **Payment received**.
7. POS announces the authoritative paid amount once.
8. Repeated status polling does not repeat the same announcement.
9. Disable sound and confirm no further speech is produced.

The speaker does not replace the existing manual/automatic completion business policy.
