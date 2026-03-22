#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/dev/use-ngrok-mode.sh https://your-ngrok-url.ngrok-free.app"
  exit 1
fi

PUBLIC_URL="$1"

if [[ "$PUBLIC_URL" != https://* ]]; then
  echo "Please use the https ngrok URL."
  exit 1
fi

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
' "$ENV_FILE" "$PUBLIC_URL"

cd "$ROOT_DIR"
php artisan optimize:clear >/dev/null

echo "Phone / ngrok mode enabled."
echo "APP_URL=$PUBLIC_URL"
echo "Next:"
echo "  php artisan serve --host=0.0.0.0 --port=8001"
echo "  ngrok http 8001"
