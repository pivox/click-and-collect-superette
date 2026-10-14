#!/usr/bin/env bash
# backup-postgres.sh - encrypted, verified PostgreSQL backup (pg_dump custom format).
#
# Output: ${BACKUP_DIR}/${BACKUP_PREFIX}-postgres-<UTC timestamp>.dump.age (+ .sha256 sidecar)
# The absolute path of the final file is the ONLY thing written to stdout.
set -euo pipefail

SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
  cat >&2 <<USAGE
Usage: ${SCRIPT_NAME} [-h|--help]

Dumps the PostgreSQL database (pg_dump -Fc), verifies it, encrypts it with age
and stores it in BACKUP_DIR. No secret is ever passed on the command line.

Required environment:
  BACKUP_DIR                   Destination directory for backups.
  BACKUP_AGE_RECIPIENT         age public key (age1...) OR
  BACKUP_AGE_RECIPIENTS_FILE   file with one age/ssh recipient per line.
  DATABASE_URL                 postgresql://user:pass@host:5432/db?serverVersion=16
                               (or the standard libpq variables PGHOST, PGPORT,
                               PGUSER, PGPASSWORD / ~/.pgpass, PGDATABASE).

Optional environment:
  BACKUP_PREFIX                File name prefix (default: clickcollect).
  BACKUP_TMP_DIR               Scratch dir for the transient plaintext dump
                               (default: BACKUP_DIR/.tmp, mode 700).
  BACKUP_EXCLUDE_MESSENGER     1 = exclude messenger_messages DATA from the dump
                               (default: 0, the dump is complete).
  BACKUP_VERIFY_FULL           1 = read the whole archive back with pg_restore
                               (default: 1; 0 only checks the table of contents).
  BACKUP_MIN_DUMP_BYTES        Minimum plaintext dump size (default: 10240).
  BACKUP_COMPRESSION_LEVEL     pg_dump -Z level 0-9 (default: 6).
USAGE
}

log() { printf '%s [%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${SCRIPT_NAME}" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

# Percent-decode a URL component (the "+" sign is kept literal in userinfo).
urldecode() {
  local s=${1//\\/\\\\}
  s=${s//%/\\x}
  printf '%b' "${s}"
}

# Export libpq variables from a DSN. The password goes into PGPASSWORD (environment),
# never into an argument list visible with ps.
export_pg_env_from_url() {
  local url=$1
  local re='^(postgres|postgresql)://([^:@/]*)(:([^@]*))?@(\[[^]]+\]|[^:/?]+)(:([0-9]+))?/([^?]*)(\?.*)?$'
  [[ ${url} =~ ${re} ]] || return 1
  local host=${BASH_REMATCH[5]}
  host=${host#[}
  host=${host%]}
  export PGUSER PGHOST PGPORT PGDATABASE
  PGUSER=$(urldecode "${BASH_REMATCH[2]}")
  PGHOST=${host}
  PGPORT=${BASH_REMATCH[7]:-5432}
  PGDATABASE=$(urldecode "${BASH_REMATCH[8]}")
  if [[ -n ${BASH_REMATCH[4]} ]]; then
    export PGPASSWORD
    PGPASSWORD=$(urldecode "${BASH_REMATCH[4]}")
  fi
  return 0
}

require_cmd() {
  local c
  for c in "$@"; do
    command -v "${c}" >/dev/null 2>&1 || die "required command not found: ${c}"
  done
}

TMP_DIR=""
PARTIAL=""
cleanup() {
  [[ -n ${PARTIAL} ]] && rm -f -- "${PARTIAL}"
  [[ -n ${TMP_DIR} ]] && rm -rf -- "${TMP_DIR}"
  return 0
}

main() {
  case "${1:-}" in
    -h | --help) usage; exit 0 ;;
    "") ;;
    *) usage; die "unexpected argument: $1" ;;
  esac

  [[ -n ${BACKUP_DIR:-} ]] || { usage; die "BACKUP_DIR is not set"; }
  if [[ -z ${BACKUP_AGE_RECIPIENT:-} && -z ${BACKUP_AGE_RECIPIENTS_FILE:-} ]]; then
    usage
    die "set BACKUP_AGE_RECIPIENT or BACKUP_AGE_RECIPIENTS_FILE (public key only, never the private key)"
  fi
  if [[ -n ${BACKUP_AGE_RECIPIENTS_FILE:-} && ! -r ${BACKUP_AGE_RECIPIENTS_FILE} ]]; then
    die "BACKUP_AGE_RECIPIENTS_FILE is not readable: ${BACKUP_AGE_RECIPIENTS_FILE}"
  fi
  if [[ -n ${BACKUP_AGE_RECIPIENT:-} && ! ${BACKUP_AGE_RECIPIENT} =~ ^(age1|ssh-) ]]; then
    die "BACKUP_AGE_RECIPIENT must be an age public key (age1...) or an ssh public key"
  fi
  require_cmd pg_dump pg_restore age sha256sum mktemp stat

  if [[ -n ${DATABASE_URL:-} ]]; then
    export_pg_env_from_url "${DATABASE_URL}" \
      || die "DATABASE_URL is not a supported postgresql://user:pass@host[:port]/db DSN"
  fi
  [[ -n ${PGDATABASE:-} ]] || die "no database: set DATABASE_URL or PGDATABASE"
  export PGCONNECT_TIMEOUT=${PGCONNECT_TIMEOUT:-10}

  local prefix=${BACKUP_PREFIX:-clickcollect}
  local level=${BACKUP_COMPRESSION_LEVEL:-6}
  local min_bytes=${BACKUP_MIN_DUMP_BYTES:-10240}
  [[ ${prefix} =~ ^[A-Za-z0-9_.]+$ ]] || die "BACKUP_PREFIX may only contain [A-Za-z0-9_.]"
  [[ ${level} =~ ^[0-9]$ ]] || die "BACKUP_COMPRESSION_LEVEL must be 0-9"
  [[ ${min_bytes} =~ ^[0-9]+$ ]] || die "BACKUP_MIN_DUMP_BYTES must be an integer"

  umask 077
  mkdir -p -- "${BACKUP_DIR}"
  chmod 700 -- "${BACKUP_DIR}" 2>/dev/null || true

  # Serialize runs so two cron invocations cannot overlap.
  if command -v flock >/dev/null 2>&1; then
    exec 9>"${BACKUP_DIR}/.${SCRIPT_NAME}.lock"
    flock -n 9 || die "another ${SCRIPT_NAME} run is in progress"
  fi

  local tmp_base=${BACKUP_TMP_DIR:-${BACKUP_DIR}/.tmp}
  mkdir -p -- "${tmp_base}"
  TMP_DIR=$(mktemp -d "${tmp_base}/pgbackup.XXXXXX")
  trap cleanup EXIT

  local ts final dump toc
  ts=$(date -u +%Y%m%dT%H%M%SZ)
  final="${BACKUP_DIR}/${prefix}-postgres-${ts}.dump.age"
  if [[ -e ${final} ]]; then
    log "backup already exists, nothing to do: ${final}"
    printf '%s\n' "${final}"
    return 0
  fi
  dump="${TMP_DIR}/dump.fc"
  toc="${TMP_DIR}/toc.txt"

  local -a dump_args=(--format=custom "--compress=${level}" --no-password "--file=${dump}")
  if [[ ${BACKUP_EXCLUDE_MESSENGER:-0} == 1 ]]; then
    dump_args+=(--exclude-table-data=messenger_messages)
    log "messenger_messages DATA is excluded from this dump (BACKUP_EXCLUDE_MESSENGER=1)"
  fi

  log "dumping database '${PGDATABASE}' from ${PGHOST:-<socket>}:${PGPORT:-5432} as '${PGUSER:-<default>}'"
  pg_dump "${dump_args[@]}"

  # --- Verification: never declare success on an empty or truncated dump ---
  local size
  size=$(stat -c %s -- "${dump}")
  (( size >= min_bytes )) || die "dump is suspiciously small (${size} bytes < ${min_bytes})"
  pg_restore --list "${dump}" >"${toc}" || die "pg_restore cannot read the dump table of contents"
  grep -q ' TABLE DATA ' "${toc}" || die "dump contains no table data"
  if grep -q ' EXTENSION - unaccent' "${toc}"; then
    log "check: extension unaccent is present in the dump"
  else
    log "WARNING: extension unaccent not found in the dump; the restored database would lack it (migration Version20260526100000)"
  fi
  if [[ ${BACKUP_VERIFY_FULL:-1} == 1 ]]; then
    log "verifying the full archive by reading it back (pg_restore -> /dev/null)"
    pg_restore --file=/dev/null "${dump}" || die "full read-back of the dump failed (truncated or corrupt)"
  fi
  log "dump verified: ${size} bytes, $(grep -vc '^;' "${toc}") TOC entries"

  # --- Encryption (age) into a .partial file, then atomic rename ---
  PARTIAL="${final}.partial"
  local -a age_args=()
  if [[ -n ${BACKUP_AGE_RECIPIENT:-} ]]; then age_args+=(-r "${BACKUP_AGE_RECIPIENT}"); fi
  if [[ -n ${BACKUP_AGE_RECIPIENTS_FILE:-} ]]; then age_args+=(-R "${BACKUP_AGE_RECIPIENTS_FILE}"); fi
  age "${age_args[@]}" -o "${PARTIAL}" "${dump}"

  [[ -s ${PARTIAL} ]] || die "encrypted file is empty"
  [[ $(head -c 21 "${PARTIAL}") == "age-encryption.org/v1" ]] || die "encrypted file has no age header"
  (( $(stat -c %s -- "${PARTIAL}") > size / 2 )) \
    || log "WARNING: encrypted file is much smaller than the dump, check the result"

  mv -- "${PARTIAL}" "${final}"
  PARTIAL=""
  (cd -- "${BACKUP_DIR}" && sha256sum -- "$(basename "${final}")" >"$(basename "${final}").sha256")

  log "backup OK: ${final} ($(stat -c %s -- "${final}") bytes encrypted)"
  printf '%s\n' "${final}"
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
  main "$@"
fi
