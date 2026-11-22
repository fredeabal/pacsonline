#!/usr/bin/env bash
set -euo pipefail

# Script de respaldo diario para PACS Online (SQLite + Orthanc DICOM)
PACS_DIR="${PACS_DIR:-/opt/pacs}"
BACKUP_DIR="${BACKUP_DIR:-/opt/pacs/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
TMP_DIR="$(mktemp -d)"

mkdir -p "$BACKUP_DIR"

# 1. Copia consistente de SQLite de CodeIgniter
if [ -f "$PACS_DIR/app/writable/pacs.sqlite" ]; then
    if command -v sqlite3 >/dev/null 2>&1; then
        sqlite3 "$PACS_DIR/app/writable/pacs.sqlite" ".backup '$TMP_DIR/pacs.sqlite'"
    else
        cp "$PACS_DIR/app/writable/pacs.sqlite" "$TMP_DIR/pacs.sqlite"
    fi
fi

# 2. Empaquetar SQLite + datos de Orthanc + .env
ARCHIVE_PATH="$BACKUP_DIR/pacs_backup_${TIMESTAMP}.tar.gz"
tar -czf "$ARCHIVE_PATH" \
    -C "$TMP_DIR" . \
    -C "$PACS_DIR" data/orthanc .env 2>/dev/null || tar -czf "$ARCHIVE_PATH" -C "$TMP_DIR" .

rm -rf "$TMP_DIR"

# 3. Cifrado opcional AES-256 si BACKUP_PASSPHRASE está definido en .env
if [ -f "$PACS_DIR/.env" ]; then
    PASSPHRASE=$(grep -E '^BACKUP_PASSPHRASE=' "$PACS_DIR/.env" | cut -d'=' -f2- || true)
    if [ -n "$PASSPHRASE" ]; then
        openssl enc -aes-256-cbc -pbkdf2 -salt -in "$ARCHIVE_PATH" -out "${ARCHIVE_PATH}.enc" -pass pass:"$PASSPHRASE"
        rm -f "$ARCHIVE_PATH"
        ARCHIVE_PATH="${ARCHIVE_PATH}.enc"
    fi
fi

# 4. Rotación de backups antiguos
find "$BACKUP_DIR" -type f -name "pacs_backup_*" -mtime +"$RETENTION_DAYS" -delete

echo "[OK] Backup creado: $ARCHIVE_PATH"
