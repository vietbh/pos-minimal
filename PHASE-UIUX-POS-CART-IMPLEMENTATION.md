# POS Mobile Cart UX — Implementation

This change is presentation-only and uses the existing POS checkout/cart state.

## Changed

- Mobile POS cart is now a compact sticky bottom cart affordance.
- Tapping `Xem giỏ` opens the existing cart as a bottom sheet.
- Cart quantity/remove controls remain the existing Stimulus actions and targets.
- The existing server-confirmed total remains the source of truth.
- `Thanh toán` in the cart sheet only scrolls to the existing payment section; it does not submit or change checkout semantics.
- POS mobile bottom navigation is hidden while using the POS workspace so it does not compete with the cart CTA.
- Desktop POS remains the existing two-column layout.
- iOS safe-area, keyboard-sized controls, Escape/backdrop close and reduced-motion behavior are handled at the presentation layer.

## Business invariants

No changes were made to checkout, payment, stock, order state, idempotency, concurrency, permissions, persistence, webhook/reconciliation, debt or audit logic.


## Product Catalog Mobile Refinement

- Mobile product catalog now uses a compact search toolbar with clear action.
- Active categories are presented as horizontally scrollable chips on mobile; the existing category `<select>` remains the desktop control.
- Product rows use a compact thumb, prominent price, category/SKU/unit metadata, stock badge, and a 48px circular add button.
- Products already in the cart show a small `Đã thêm N` indicator. This is presentation derived from the existing client cart state only.
- The catalog endpoint, search debounce, pagination, category filtering, stock validation, cart persistence and add-to-cart action are unchanged.
- No product/image API, database, pricing, stock, checkout or payment logic was changed.
