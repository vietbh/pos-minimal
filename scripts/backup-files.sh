#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
STORAGE_ROOT="${PRODUCT_IMAGE_STORAGE_ROOT:-var/storage}"
case "$STORAGE_ROOT" in /*) ;; *) STORAGE_ROOT="$PROJECT_DIR/$STORAGE_ROOT" ;; esac
STORAGE_ROOT="$(cd "$STORAGE_ROOT" && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/var/backups/files}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

if [[ ! -d "$STORAGE_ROOT" ]]; then
  echo "Storage root does not exist: $STORAGE_ROOT" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$BACKUP_DIR/product-images-${STAMP}.tar.gz"

tar -C "$STORAGE_ROOT" -czf "$OUT" .
test -s "$OUT"
chmod 600 "$OUT"
find "$BACKUP_DIR" -type f -name 'product-images-*.tar.gz' -mtime "+$RETENTION_DAYS" -delete

echo "File backup created: $OUT"
