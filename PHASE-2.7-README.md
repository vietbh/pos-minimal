# Phase 2.7 — Order Management

Implemented the read-side Order Management flow:

- GET /orders — paginated order list
- search by order number, customer name, customer phone
- filters: status and created date range
- GET /orders/{id} — order detail
- historical OrderItem snapshots
- payment history
- derived paid/debt amounts from Order payments
- originating debt details when present
- ORDER_VIEW permission enforced on both routes
- mobile-first Twig UI
- no schema/migration changes required
- application query handlers + DTOs
- integration and HTTP tests

No cancel/refund mutation is included in this phase because the source business specification leaves the financial/stock impact of cancellation/refund as an open business decision. The existing ORDER_CANCEL / ORDER_REFUND permissions remain available for the later mutation phase.
