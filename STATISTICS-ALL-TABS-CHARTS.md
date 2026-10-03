Statistics update

- “All sales points” is explicitly selected when sales_point_id is null; an empty query value is treated as no sales-point restriction by the controller.
- Each detail tab renders a chart from its own backend response and keeps the tab state in the filter form.
- Chart values are derived from returned rows; no demo/fake records are introduced.

After extracting at project root run: npm run build && APP_ENV=dev php bin/console cache:clear
