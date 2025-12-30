#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"

echo "==> Preparing .env"
if [[ ! -f "${ROOT}/.env" ]]; then
  cp "${ROOT}/.env.example" "${ROOT}/.env"
fi

echo "==> Creating SQLite database files"
mkdir -p "${ROOT}/database"
touch "${ROOT}/database/database.sqlite"
touch "${ROOT}/database/sandbox.sqlite"

echo "==> Configuring SQLite + Sandbox env vars"
python3 - <<'PY'
import os, re, pathlib

root = pathlib.Path(os.getcwd())
env_path = root / ".env"
text = env_path.read_text()

def set_kv(key, value):
    global text
    pattern = re.compile(rf"^{re.escape(key)}=.*$", re.M)
    line = f"{key}={value}"
    if pattern.search(text):
        text = pattern.sub(line, text)
    else:
        if not text.endswith("\n"):
            text += "\n"
        text += line + "\n"

set_kv("DB_CONNECTION", "sqlite")
set_kv("DB_DATABASE", str(root / "database" / "database.sqlite"))

set_kv("SANDBOX_ENABLED", "true")
set_kv("SANDBOX_DB_DATABASE", str(root / "database" / "sandbox.sqlite"))

env_path.write_text(text)
PY

echo "==> Installing Composer dependencies"
composer install --no-interaction --prefer-dist

echo "==> Generating app key (if missing)"
php artisan key:generate --force --no-interaction

echo "==> Running migrations (app + settings)"
php artisan migrate --force --no-interaction
php artisan migrate --path=database/settings --force --no-interaction

echo "==> Reset & seed sandbox database"
php artisan sandbox:reset --seed

echo "==> Done. Start the server with:"
echo "    php artisan serve --host=0.0.0.0 --port=8000"

