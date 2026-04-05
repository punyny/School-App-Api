#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

LOCAL_URL="${1:-http://127.0.0.1:8001}"

read_env_value() {
  local key="$1"
  local default_value="$2"
  local value

  value="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f 2- || true)"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"

  if [[ -z "$value" ]]; then
    value="$default_value"
  fi

  printf '%s' "$value"
}

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing .env file at $ENV_FILE"
  exit 1
fi

php -r '
$file = $argv[1];
$url = $argv[2];
$contents = file_get_contents($file);
if ($contents === false) {
    fwrite(STDERR, "Unable to read .env file\n");
    exit(1);
}
if (preg_match("/^APP_URL=.*/m", $contents) === 1) {
    $contents = preg_replace("/^APP_URL=.*/m", "APP_URL=".$url, $contents, 1);
} else {
    $contents .= PHP_EOL."APP_URL=".$url.PHP_EOL;
}
if (file_put_contents($file, $contents) === false) {
    fwrite(STDERR, "Unable to write .env file\n");
    exit(1);
}
' "$ENV_FILE" "$LOCAL_URL"

cd "$ROOT_DIR"
php artisan optimize:clear >/dev/null

UPLOAD_MAX_FILESIZE="$(read_env_value "UPLOAD_MAX_FILESIZE" "20M")"
POST_MAX_SIZE="$(read_env_value "POST_MAX_SIZE" "20M")"

echo "Local mode enabled."
echo "APP_URL=$LOCAL_URL"
echo "UPLOAD_MAX_FILESIZE=$UPLOAD_MAX_FILESIZE"
echo "POST_MAX_SIZE=$POST_MAX_SIZE"
echo "Next:"
echo "  php -d upload_max_filesize=$UPLOAD_MAX_FILESIZE -d post_max_size=$POST_MAX_SIZE artisan serve --host=0.0.0.0 --port=8001"
