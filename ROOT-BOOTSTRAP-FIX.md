# ROOT bootstrap hotfix

The BankNotificationWebhookController has no `$webhookToken` constructor argument because the webhook token is stored in PaymentWebhookSettings. Therefore services.yaml must NOT configure `$webhookToken` for that controller.

Correct secrets:

```bash
php bin/console secrets:set ROOT_ACCOUNT_USERNAME
php bin/console secrets:set ROOT_ACCOUNT_PASSWORD
```

`secrets:set` takes the secret NAME as its argument and then prompts for the VALUE. Do not use `root` or `root123` as the secret names unless those are intentionally separate secret variables.

Bootstrap:

```bash
php bin/console app:bootstrap-root
```

ROOT password must be at least 12 characters. The command stores only Symfony's password hash in the users table.
