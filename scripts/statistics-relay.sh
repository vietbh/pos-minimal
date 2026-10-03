#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
LOCK_FILE="${STATISTICS_RELAY_LOCK_FILE:-/tmp/mobile-pos-statistics-relay.lock}"

cd "$PROJECT_DIR"
exec flock -n "$LOCK_FILE" bash -c '
  set -euo pipefail
  "$1" bin/console app:statistics:dispatch-outbox --env=prod
  "$1" bin/console app:statistics:publish-notifications --env=prod
' _ "$PHP_BIN"
