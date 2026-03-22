#!/usr/bin/env bash

set -euo pipefail

PORT="${1:-8001}"

if ! command -v ngrok >/dev/null 2>&1; then
  echo "ngrok is not installed."
  echo "Install with: brew install ngrok/ngrok/ngrok"
  echo "Then add token: ngrok config add-authtoken YOUR_TOKEN"
  exit 1
fi

exec ngrok http "$PORT"
