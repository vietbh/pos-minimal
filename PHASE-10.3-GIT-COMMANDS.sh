#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# Phase 10.3 only. Do not use `git add .` because unrelated migration/
# compose/runtime changes may already exist in the working tree.
git add \
  PHASE-10.3-IMPLEMENTATION.md \
  PHASE-10.3-GIT-COMMANDS.sh \
  assets/controllers/pos_checkout_controller.js \
  public/assets/controllers/pos_checkout_controller-yJcqcjI.js \
  assets/styles/app.css \
  templates/pos/index.html.twig \
  translations/messages.en.yaml \
  translations/messages.vi.yaml \
  tests/Frontend/Phase103PosCheckoutIntegrationContractTest.php

echo '--- staged check ---'
git diff --cached --check

echo '--- staged status ---'
git status --short

echo '--- staged stat ---'
git diff --cached --stat

echo
git diff --cached --name-only

echo
echo 'If the staged files are correct, commit with:'
echo 'git commit -m "feat(ui): integrate POS checkout experience"'
