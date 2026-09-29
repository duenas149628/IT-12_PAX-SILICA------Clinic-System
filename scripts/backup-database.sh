#!/usr/bin/env bash
set -Eeuo pipefail

: "${DB_NAME:?Set DB_NAME}"
: "${MYSQL_CNF:?Set MYSQL_CNF to a protected MySQL option file}"
: "${BACKUP_DIR:?Set BACKUP_DIR outside the web root}"
: "${AGE_RECIPIENT:?Set AGE_RECIPIENT to the clinic's backup public key}"

for tool in mysqldump gzip age; do
    command -v "$tool" >/dev/null 2>&1 || { echo "Missing required command: $tool" >&2; exit 1; }
done
[[ -r "$MYSQL_CNF" ]] || { echo "Cannot read MYSQL_CNF" >&2; exit 1; }

umask 077
mkdir -p -- "$BACKUP_DIR"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
output="$BACKUP_DIR/${DB_NAME}-${timestamp}-$$.sql.gz.age"
temporary="${output}.tmp"
trap 'rm -f -- "$temporary"' EXIT

mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --routines --triggers "$DB_NAME" \
    | gzip -c \
    | age --encrypt --recipient "$AGE_RECIPIENT" --output "$temporary"

[[ -s "$temporary" ]] || { echo 'Backup archive is empty.' >&2; exit 1; }
mv -- "$temporary" "$output"
chmod 600 -- "$output"
trap - EXIT
printf 'Encrypted backup created: %s\n' "$output"
