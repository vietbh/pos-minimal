#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/var/backups/db}"
DATABASE_URL_VALUE="${DATABASE_URL:?DATABASE_URL is required}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-$(command -v mysqldump)}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

TMP_CNF="$(mktemp)"
TMP_OUT="$(mktemp --suffix=.sql.gz)"

cleanup() {
    rm -f "$TMP_CNF" "$TMP_OUT"
}
trap cleanup EXIT

chmod 600 "$TMP_CNF"

php -r '
$url = parse_url($argv[1]);

$scheme = $url["scheme"] ?? "";
if (!in_array($scheme, ["mysql", "mysql2", "mariadb"], true)) {
    fwrite(STDERR, "DATABASE_URL must use a MySQL-compatible scheme\n");
    exit(1);
}

printf(
    "[client]\nuser=%s\npassword=%s\nhost=%s\nport=%d\n",
    $url["user"] ?? "",
    $url["pass"] ?? "",
    $url["host"] ?? "127.0.0.1",
    $url["port"] ?? 3306,
);
' "$DATABASE_URL_VALUE" > "$TMP_CNF"

DB_NAME="$(php -r '
$url = parse_url($argv[1]);
echo ltrim($url["path"] ?? "", "/");
' "$DATABASE_URL_VALUE")"

if [[ -z "$DB_NAME" ]]; then
    echo "DATABASE_URL does not contain a database name." >&2
    exit 1
fi

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$BACKUP_DIR/mobile-pos-${STAMP}.sql.gz"

"$MYSQLDUMP_BIN" \
    --defaults-extra-file="$TMP_CNF" \
    --single-transaction \
    --no-tablespaces \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    "$DB_NAME" \
    | gzip -9 > "$TMP_OUT"

test -s "$TMP_OUT"

mv "$TMP_OUT" "$OUT"
chmod 600 "$OUT"

find "$BACKUP_DIR" \
    -type f \
    -name 'mobile-pos-*.sql.gz' \
    -mtime "+$RETENTION_DAYS" \
    -delete

echo "Database backup created: $OUT"
