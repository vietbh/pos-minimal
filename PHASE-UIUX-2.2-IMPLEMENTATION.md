# UI/UX 2.2 — Application Shell + Navigation UX

Implemented on top of the supplied UI/UX base ZIP.

## Scope

- Authenticated application shell with semantic header/nav/main landmarks.
- Desktop sidebar and mobile top navigation.
- Permission-aware navigation using existing Symfony security attributes.
- Active navigation state with `aria-current="page"`.
- Mobile menu interaction via Stimulus only; authorization remains server-side.
- Logout presented as backend Symfony Security action via POST forms while the existing GET logout route remains unchanged for compatibility.
- Translation coverage for shell/navigation/access-denied/home UI in English and Vietnamese.
- 403 UX template with a safe generic message and Home action.
- Existing Application/Home remains separate from the POS workspace.
- Existing business/security/domain behavior was not changed.

## Changed files

- `templates/layout/authenticated.html.twig`
- `templates/application/home.html.twig`
- `templates/base.html.twig`
- `templates/bundles/TwigBundle/Exception/error403.html.twig`
- `assets/controllers/navigation_controller.js`
- `assets/app.css`
- `translations/messages.en.yaml`
- `translations/messages.vi.yaml`
- `tests/Integration/Security/AuthenticationTest.php`

## Verification

Static checks completed in the supplied environment:

- PHP syntax check for changed PHP test/source files: passed.
- JavaScript syntax check for `navigation_controller.js`: passed.
- YAML parsing for `messages.en.yaml` and `messages.vi.yaml`: passed.

The supplied archive does not contain `vendor/`, so Symfony Twig/container lint and PHPUnit could not be executed in this environment.
