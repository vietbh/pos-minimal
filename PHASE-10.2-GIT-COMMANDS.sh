#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# Stage only Phase 10.2 files. Do not stage unrelated migration/compose work.
git add \
  PHASE-10.2-IMPLEMENTATION.md \
  assets/controllers/navigation_controller.js \
  assets/styles/app.css \
  templates/base.html.twig \
  templates/layout/authenticated.html.twig \
  templates/layout/pos.html.twig \
  tests/Frontend/AuthenticatedShellNavigationContractTest.php \
  translations/messages.en.yaml \
  translations/messages.vi.yaml

git diff --cached --check
git status --short

git diff --cached --stat

git commit -m "feat(ui): harden authenticated shell navigation"
