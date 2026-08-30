#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="${1:?usage: restore-db.sh /path/to/backup.sql.gz}"
DATABASE_URL_VALUE="${DATABASE_URL:?DATABASE_URL is required}"
MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql)}"

if [[ "${CONFIRM_RESTORE:-}" != "YES" ]]; then
  echo "Refusing destructive restore. Set CONFIRM_RESTORE=YES explicitly." >&2
  exit 2
fi

test -f "$BACKUP_FILE"
TMP_CNF="$(mktemp)"
trap 'rm -f "$TMP_CNF"' EXIT
chmod 600 "$TMP_CNF"

python3 - "$DATABASE_URL_VALUE" > "$TMP_CNF" <<'PY'
import sys
from urllib.parse import urlparse, unquote
u = urlparse(sys.argv[1])
print('[client]')
print('user=' + unquote(u.username or ''))
print('password=' + unquote(u.password or ''))
print('host=' + (u.hostname or '127.0.0.1'))
print('port=' + str(u.port or 3306))
print('database=' + unquote((u.path or '/').lstrip('/')))
PY

gzip -dc "$BACKUP_FILE" | "$MYSQL_BIN" --defaults-extra-file="$TMP_CNF"
echo "Database restore completed from: $BACKUP_FILE"
