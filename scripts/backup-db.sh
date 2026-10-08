#!/usr/bin/env bash
#
# 本番 SQLite の定期バックアップ。
# app コンテナ内で sqlite3 の .backup（WAL でも一貫したコピーになる）を実行し、gzip して保存する。
# 保存先は Lightsail インスタンス内の ~/db-backups で、直近 7 日分のみ保持する。
#
# 使い方（Lightsail インスタンス上、cron から毎日実行する想定）:
#   30 4 * * * /home/ubuntu/katsuooool/scripts/backup-db.sh >> /home/ubuntu/db-backups/katsuooool-backup.log 2>&1

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$REPO_DIR/compose.prod.yaml"
BACKUP_DIR="$HOME/db-backups"
RETENTION_DAYS=7
DB_PATH=/var/www/html/storage/database/database.sqlite

mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
DEST="$BACKUP_DIR/katsuooool-db-$TIMESTAMP.sqlite.gz"

cd "$REPO_DIR"

docker compose -f "$COMPOSE_FILE" exec -T app sh -c \
  "sqlite3 $DB_PATH '.backup /tmp/backup.sqlite' && cat /tmp/backup.sqlite && rm -f /tmp/backup.sqlite" \
  | gzip > "$DEST"

find "$BACKUP_DIR" -name 'katsuooool-db-*.sqlite.gz' -mtime "+${RETENTION_DAYS}" -delete

echo "$(date '+%Y-%m-%d %H:%M:%S') Backup created: $DEST"
