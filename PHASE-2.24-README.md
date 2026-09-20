

## Phase 2.24 revision — PAY reference is authoritative

MacroDroid now needs only the notification content:

```json
{"content":"5CC393D6E1DF Chuyển khoản cho BUIHOANGVIET"}
```

Optional `eventId` may be supplied for provider idempotency. `amount` and `bankAccountId`
are not required and are not trusted for payment matching. The backend extracts and
canonicalizes `5CC393D6E1DF`, `5CC393D6E1DF`, or `PAY 5CC393D6E1DF` to
`5CC393D6E1DF`, then loads the POS Payment by its unique reference. The payment's
stored amount and bank account are used as authoritative financial data.
