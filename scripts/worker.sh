#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
LOCK_FILE="${WORKER_LOCK_FILE:-/tmp/mobile-pos-worker.lock}"

cd "$PROJECT_DIR"
exec flock -n "$LOCK_FILE" "$PHP_BIN" bin/console messenger:consume async --env=prod --limit="${WORKER_LIMIT:-100}" --time-limit="${WORKER_TIME_LIMIT:-300}" --memory-limit="${WORKER_MEMORY_LIMIT:-128M}"
