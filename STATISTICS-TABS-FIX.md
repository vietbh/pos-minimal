Statistics tabs fix

Files are archived flat relative to the Symfony project root.

Apply from the project root:
  unzip -o /path/to/mobile-pos-statistics-tabs-fix.zip
  npm run build
  APP_ENV=prod php bin/console cache:clear

Then hard-refresh the browser (Ctrl+Shift+R).

The fix no longer hides entire chart/detail/list wrappers whenever the active tab is not Overview. It filters category cards and only hides a wrapper when every category card inside it is hidden. Backend queries and chart data are unchanged.
