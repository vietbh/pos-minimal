# Protected ROOT bootstrap

Do not hardcode the ROOT password in PHP, Twig, fixtures, or committed `.env` files.

Provide these at runtime, preferably through Symfony Secrets or the deployment secret store:

With Symfony Secrets, for example:

```bash
php bin/console secrets:set ROOT_ACCOUNT_USERNAME
php bin/console secrets:set ROOT_ACCOUNT_PASSWORD
```

Or provide the same names as process/container environment variables.

- `ROOT_ACCOUNT_USERNAME`
- `ROOT_ACCOUNT_PASSWORD`

Then run:

```bash
php bin/console app:bootstrap-root
```

The command is idempotent for the configured ROOT identity. It refuses to create a second ROOT account and refuses to take over a username already owned by a non-ROOT account.

The plaintext password is never written to the database or audit log. The `users.password_hash` column stores only Symfony's password hash.

For development/test demo users, the legacy `app:create-demo-users` command now also expects runtime secrets:

- `POS_DEMO_ADMIN_PASSWORD`
- `POS_DEMO_USER_PASSWORD`

This prevents demo passwords from being embedded in source code.
