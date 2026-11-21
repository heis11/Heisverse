#!/bin/sh
# HEISVERSE Database Restore for Linux/Render/Docker
FILE=${1:-"database.sql"}

if [ ! -f "$FILE" ]; then
    echo "❌ File $FILE not found."
    exit 1
fi

echo "⚠️ Restoring database from $FILE..."
docker compose exec -T db mysql -u root -pheisverse_root_pass_2026 heisverse < "$FILE"

if [ $? -eq 0 ]; then
    echo "✅ Database restored successfully."
else
    echo "❌ Restore failed."
    exit 1
fi
