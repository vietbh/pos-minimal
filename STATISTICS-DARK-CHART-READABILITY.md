Statistics chart dark-theme readability patch

- Keeps the existing per-tab chart rendering and compact layout.
- Raises chart labels, values, titles, loading and error messages to readable dark-theme contrast.
- Does not alter API routes, filtering, tab selection, or server-side data.

Apply from the Symfony project root, then rebuild frontend assets:
  unzip -o mobile-pos-statistics-dark-chart-readability-flat.zip
  npm run build
  APP_ENV=prod php bin/console cache:clear
