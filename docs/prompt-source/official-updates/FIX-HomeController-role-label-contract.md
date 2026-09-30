# Fix — Home `role_label` Twig Contract

## Symptom

Twig throws:

```text
Variable "role_label" does not exist in application/home.html.twig
```

## Required contract

`templates/application/home.html.twig` uses `role_label` and `is_admin_role`. Therefore the Home controller/view model must provide them.

Expected controller-side concept:

```php
$roleLabel = 'Nhân viên';

if ($user->hasRole(UserRole::ROOT)) {
    $roleLabel = 'Root';
} elseif ($user->hasRole(UserRole::ADMIN)) {
    $roleLabel = 'Quản trị viên';
}

return $this->render('application/home.html.twig', [
    'user' => $user,
    'dashboard' => $dashboard,
    'can_open_pos' => $this->isGranted(Permission::POS_ACCESS->value),
    'role_label' => $roleLabel,
    'is_admin_role' => $user->hasRole(UserRole::ADMIN) || $user->hasRole(UserRole::ROOT),
]);
```

Use the project's actual existing role enum and permission abstractions.

Do not patch the Twig with `|default(...)` merely to hide a missing required variable. The controller/view-model contract must be correct.

## Verification

```bash
php -l src/Controller/Application/HomeController.php
php bin/console cache:clear
```

If Symfony dependencies are installed, also run the project's existing Twig/HTTP/PHPUnit tests.
