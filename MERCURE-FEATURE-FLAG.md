# Mercure feature flag (default OFF)

This is a flat patch: files are placed at their project-relative paths. It does not include `.env` or any secrets.

- Mercure is disabled by default (`app.mercure_enabled_default: false`).
- To explicitly enable it in an environment, set `MERCURE_ENABLED=1` and configure `MERCURE_PUBLISH_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`, and `APP_PUBLIC_URL`.
- To disable it, leave `MERCURE_ENABLED` unset or set `MERCURE_ENABLED=0`.
- When disabled, the statistics page does not open an EventSource and the backend publisher does not publish Mercure events or issue the subscriber cookie. Existing polling logic (including payment-status polling) is unchanged.

After applying the patch, clear Symfony cache as appropriate for your environment, e.g. `APP_ENV=prod php bin/console cache:clear`.
