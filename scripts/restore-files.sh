#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="${1:?usage: restore-files.sh /path/to/product-images.tar.gz}"
STORAGE_ROOT="${PRODUCT_IMAGE_STORAGE_ROOT:-var/storage}"
PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
case "$STORAGE_ROOT" in /*) ;; *) STORAGE_ROOT="$PROJECT_DIR/$STORAGE_ROOT" ;; esac
STORAGE_ROOT="$(mkdir -p "$STORAGE_ROOT" && cd "$STORAGE_ROOT" && pwd)"

if [[ "${CONFIRM_RESTORE:-}" != "YES" ]]; then
  echo "Refusing restore. Set CONFIRM_RESTORE=YES explicitly." >&2
  exit 2
fi

test -f "$BACKUP_FILE"
mkdir -p "$STORAGE_ROOT"

tar -tzf "$BACKUP_FILE" >/dev/null
tar -C "$STORAGE_ROOT" -xzf "$BACKUP_FILE"
echo "File restore completed into: $STORAGE_ROOT"
