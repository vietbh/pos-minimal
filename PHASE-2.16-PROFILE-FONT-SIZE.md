# PHASE 2.16 — Profile + Font Size UX

## Delivered
- Basic authenticated profile page at `/app/profile`.
- Editable username with CSRF protection.
- Read-only role and active status display.
- Persistent font-size preference: small / medium / large.
- Font-size preference is applied globally from the authenticated user's setting.
- Global authenticated navigation includes `Tài khoản`.
- Security section explains Remember Me and logout behavior.
- Mobile-first responsive profile layout.

## Persistence
Migration: `Version20260917153000`
- Adds `users.font_size VARCHAR(10) NOT NULL DEFAULT 'medium'`.

## Design
- Small: 14px base.
- Medium: 16px base.
- Large: 19px base.
- Existing touch targets and control sizing are retained.

## Validation
- PHP syntax checked for all changed PHP files.
- Full PHPUnit/Twig runtime validation should be run in the project's normal environment.
