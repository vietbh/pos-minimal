#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

git add \
  PHASE-10.5-IMPLEMENTATION.md \
  PHASE-10.5-GIT-COMMANDS.sh \
  assets/controllers/stock_adjust_controller.js \
  assets/styles/app.css \
  templates/admin/product/index.html.twig \
  templates/admin/product/show.html.twig \
  templates/admin/product/form.html.twig \
  templates/admin/stock/index.html.twig \
  templates/admin/stock/show.html.twig \
  translations/messages.en.yaml \
  translations/messages.vi.yaml \
  tests/Frontend/Phase105ProductStockUiContractTest.php

git diff --cached --check
git status
git diff --cached --stat

git commit -m "feat(ui): integrate product and stock experience"
