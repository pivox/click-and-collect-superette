#!/usr/bin/env sh
set -eu

HOST="${NGROK_HOST:-localhost}"
PORT="${NGROK_PORT:-3000}"
LOG_FILE="${NGROK_LOG_FILE:-/tmp/kadhia-ngrok-frontend.log}"
TARGET_URL="http://${HOST}:${PORT}"
NGROK_API_URL="${NGROK_API_URL:-http://127.0.0.1:4040/api/tunnels}"
MODE="${1:---detach}"

usage() {
  echo "Usage: $0 [--detach|--foreground|--help]"
  echo
  echo "Environment:"
  echo "  NGROK_HOST      Local host to expose. Default: localhost"
  echo "  NGROK_PORT      Local port to expose. Default: 3000"
  echo "  NGROK_LOG_FILE  Log file used with --detach. Default: /tmp/kadhia-ngrok-frontend.log"
}

public_urls() {
  curl -fsS --max-time 2 "${NGROK_API_URL}" 2>/dev/null \
    | sed -n 's/.*"public_url":"\([^"]*\)".*/\1/p'
}

wait_for_tunnel() {
  i=0
  while [ "${i}" -lt 15 ]; do
    urls="$(public_urls || true)"
    if [ -n "${urls}" ]; then
      echo "${urls}"
      return 0
    fi
    i=$((i + 1))
    sleep 1
  done

  echo "ngrok started, but no public URL was available from ${NGROK_API_URL}." >&2
  echo "Check logs: ${LOG_FILE}" >&2
  return 1
}

check_api_proxy() {
  public_url="$1"

  if ! command -v curl >/dev/null 2>&1; then
    return 0
  fi

  if curl -fsS --max-time 10 -H 'ngrok-skip-browser-warning: true' "${public_url}/api/docs.json" >/dev/null 2>&1; then
    echo "API proxy: ${public_url}/api"
    return 0
  fi

  echo "Warning: ${public_url}/api/docs.json does not respond." >&2
  echo "Restart the frontend if next.config.mjs changed: docker compose restart frontend" >&2
}

if ! command -v ngrok >/dev/null 2>&1; then
  echo "ngrok is not installed or not available in PATH." >&2
  exit 1
fi

if command -v curl >/dev/null 2>&1; then
  if ! curl -fsS --max-time 3 "${TARGET_URL}" >/dev/null 2>&1; then
    echo "Warning: ${TARGET_URL} does not respond yet." >&2
    echo "Start the frontend first with: docker compose up frontend" >&2
  fi
fi

case "${MODE}" in
  --help|-h)
    usage
    exit 0
    ;;
  --detach)
    existing_url="$(public_urls || true)"
    if [ -n "${existing_url}" ]; then
      echo "ngrok is already running for this machine."
      echo "External URL: ${existing_url}"
      check_api_proxy "${existing_url}"
      exit 0
    fi

    echo "Starting ngrok for Kadhia frontend in background: ${TARGET_URL}"
    if command -v setsid >/dev/null 2>&1; then
      setsid ngrok http "${TARGET_URL}" --log=stdout >"${LOG_FILE}" 2>&1 </dev/null &
    else
      nohup ngrok http "${TARGET_URL}" --log=stdout >"${LOG_FILE}" 2>&1 </dev/null &
    fi
    echo "ngrok PID: $!"
    external_url="$(wait_for_tunnel)"
    echo "External URL: ${external_url}"
    check_api_proxy "${external_url}"
    ;;
  --foreground)
    echo "Starting ngrok for Kadhia frontend: ${TARGET_URL}"
    echo "Press Ctrl+C to stop the tunnel."
    exec ngrok http "${TARGET_URL}"
    ;;
  *)
    usage >&2
    exit 1
    ;;
esac
