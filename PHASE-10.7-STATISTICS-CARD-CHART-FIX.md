# Statistics card + chart fix

Applied on top of `mobile-pos-current(10).zip`.

## UI

- Widened the statistics workspace on desktop to 1180px so KPI cards do not become narrow six-column cards.
- Reworked the KPI hierarchy into a 4-column desktop grid:
  - Net sales spans two columns.
  - Payments collected and AOV occupy the remaining two columns.
  - Gross sales spans two columns as the secondary financial metric.
  - Cancelled and refunded occupy the remaining two columns.
- Preserved 2-column tablet and 1-column mobile behavior.
- Preserved existing older-adult design tokens and accessibility structure.

## ApexCharts fix

The financial chart is horizontal. ApexCharts therefore uses:

- `yaxis` for category labels.
- `xaxis` for numeric values.

The previous formatter was attached to `yaxis`, causing category names to be coerced to `0` in the rendered labels. The formatter is now attached to `xaxis`, while the category axis keeps readable labels.

No business calculation was added to the frontend. All chart values remain server-provided.
