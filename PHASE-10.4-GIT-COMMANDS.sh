#!/usr/bin/env bash
set -euo pipefail

cd ~/Documents/self-projects/mobile-pos

git add \
  PHASE-10.4-IMPLEMENTATION.md \
  assets/controllers/debt_payment_controller.js \
  assets/controllers/order_lifecycle_controller.js \
  assets/styles/app.css \
  templates/order/show.html.twig \
  templates/customer/index.html.twig \
  templates/customer/show.html.twig \
  templates/customer/form.html.twig \
  templates/debt/index.html.twig \
  templates/debt/show.html.twig \
  translations/messages.en.yaml \
  translations/messages.vi.yaml \
  tests/Frontend/Phase104OrderCustomerDebtUiContractTest.php

git diff --cached --check
git status --short
git diff --cached --stat

git diff --cached --name-only

git commit -m "feat(ui): integrate order customer debt experience"
git status
