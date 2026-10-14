#!/usr/bin/env bash
# restore-postgres.sh - restore an encrypted backup made by backup-postgres.sh.
#
# DESTRUCTIVE: the target database content is dropped and replaced (pg_restore --clean).
# The target is taken from RESTORE_DATABASE_URL and NEVER from DATABASE_URL, so that a
# shell holding production credentials cannot restore over production by accident.
set -euo pipefail

SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
  cat >&2 <<USAGE
Usage: ${SCRIPT_NAME} [--dry-run] [--force] [--with-messenger-queue] BACKUP_FILE

Arguments:
  BACKUP_FILE               .dump.age file produced by backup-postgres.sh
                            (a plain .dump is also accepted, with a warning).

Options:
  --dry-run                 Decrypt, validate and print the plan, change nothing.
  --force                   Non-interactive mode: no prompt, but RESTORE_CONFIRM_DB must
                            equal the target database name.
  --with-messenger-queue    Also restore the rows of messenger_messages. By default they
                            are SKIPPED (stale queued jobs could replay notifications or
                            fire obsolete timers).
  -h, --help                Show this help.

Required environment:
  RESTORE_DATABASE_URL      Target DSN, e.g. postgresql://user:pass@host:5432/db
                            The database must already exist (createdb) and be PostgreSQL 16.
  RESTORE_AGE_IDENTITY_FILE age private key file (or ssh private key) used to decrypt.

Optional environment:
  RESTORE_CONFIRM_DB        Required with --force: the target database name.
  RESTORE_TMP_DIR           Scratch dir for the decrypted dump (default: system tmp,
                            mode 700). Needs room for the plaintext dump.
USAGE
}

log() { printf '%s [%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${SCRIPT_NAME}" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

urldecode() {
  local s=${1//\\/\\\\}
  s=${s//%/\\x}
  printf '%b' "${s}"
}

# Export libpq variables from a DSN (password via environment only, never via argv).
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
cleanup() {
  [[ -n ${TMP_DIR} ]] && rm -rf -- "${TMP_DIR}"
  return 0
}

main() {
  local dry_run=0 force=0 with_queue=0 backup_file=""
  while (( $# > 0 )); do
    case "$1" in
      -h | --help) usage; exit 0 ;;
      --dry-run) dry_run=1 ;;
      --force) force=1 ;;
      --with-messenger-queue) with_queue=1 ;;
      -*) usage; die "unknown option: $1" ;;
      *)
        [[ -z ${backup_file} ]] || { usage; die "only one BACKUP_FILE is accepted"; }
        backup_file=$1
        ;;
    esac
    shift
  done

  [[ -n ${backup_file} ]] || { usage; die "BACKUP_FILE is required"; }
  [[ -f ${backup_file} ]] || die "backup file not found: ${backup_file}"
  [[ -n ${RESTORE_DATABASE_URL:-} ]] || { usage; die "RESTORE_DATABASE_URL is not set"; }
  [[ -n ${RESTORE_AGE_IDENTITY_FILE:-} ]] || { usage; die "RESTORE_AGE_IDENTITY_FILE is not set"; }
  [[ -r ${RESTORE_AGE_IDENTITY_FILE} ]] || die "RESTORE_AGE_IDENTITY_FILE is not readable"
  require_cmd pg_restore psql age sha256sum mktemp stat

  # Deliberately ignore any inherited PG* variables so only RESTORE_DATABASE_URL counts.
  unset PGHOST PGPORT PGUSER PGDATABASE PGPASSWORD PGSERVICE PGPASSFILE
  export_pg_env_from_url "${RESTORE_DATABASE_URL}" \
    || die "RESTORE_DATABASE_URL is not a supported postgresql://user:pass@host[:port]/db DSN"
  [[ -n ${PGDATABASE} ]] || die "RESTORE_DATABASE_URL has no database name"
  export PGCONNECT_TIMEOUT=${PGCONNECT_TIMEOUT:-10}

  umask 077
  TMP_DIR=$(mktemp -d "${RESTORE_TMP_DIR:-${TMPDIR:-/tmp}}/pgrestore.XXXXXX")
  trap cleanup EXIT
  local dump="${TMP_DIR}/dump.fc" toc="${TMP_DIR}/toc.txt" filtered="${TMP_DIR}/toc.filtered.txt"

  # --- 1. Integrity of the file itself ---
  local sidecar="${backup_file}.sha256"
  if [[ -f ${sidecar} ]]; then
    (cd -- "$(dirname -- "${backup_file}")" && sha256sum --check --status -- "$(basename -- "${sidecar}")") \
      || die "sha256 mismatch: the backup file is corrupt or truncated"
    log "sha256 sidecar verified"
  else
    log "WARNING: no .sha256 sidecar next to the backup; file integrity not pre-checked"
  fi

  # --- 2. Decrypt into the private scratch dir ---
  case "${backup_file}" in
    *.age) age --decrypt -i "${RESTORE_AGE_IDENTITY_FILE}" -o "${dump}" "${backup_file}" \
             || die "decryption failed (wrong identity?)" ;;
    *.dump) log "WARNING: plaintext dump given, skipping decryption"; cp -- "${backup_file}" "${dump}" ;;
    *) die "unsupported file extension (expected .dump.age or .dump)" ;;
  esac
  pg_restore --list "${dump}" >"${toc}" || die "decrypted file is not a valid pg_dump custom archive"

  local has_unaccent="no" has_queue_data="no" entries
  grep -q ' EXTENSION - unaccent' "${toc}" && has_unaccent="yes"
  grep -q ' TABLE DATA public messenger_messages ' "${toc}" && has_queue_data="yes"
  entries=$(grep -vc '^;' "${toc}")

  # --- 3. Messenger queue handling (selective restore via a filtered TOC) ---
  local -a list_args=()
  local queue_plan
  if (( with_queue == 1 )); then
    queue_plan="RESTORE messenger_messages rows (--with-messenger-queue)"
  elif [[ ${has_queue_data} == yes ]]; then
    grep -v ' TABLE DATA public messenger_messages ' "${toc}" >"${filtered}"
    list_args=(--use-list="${filtered}")
    queue_plan="SKIP messenger_messages rows (table is recreated empty; use --with-messenger-queue to keep them)"
  else
    queue_plan="no messenger_messages rows in this dump"
  fi

  # --- 4. Inspect the target (read-only) ---
  local target_tables="unknown (cannot connect)" reachable=0
  if target_tables=$(psql --no-psqlrc --no-password -Atq \
      -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'" 2>/dev/null); then
    reachable=1
  else
    target_tables="unknown (cannot connect)"
  fi

  # --- 5. Show the plan ---
  {
    printf '\n========== RESTORE PLAN ==========\n'
    printf 'Backup file     : %s (%s bytes)\n' "${backup_file}" "$(stat -c %s -- "${backup_file}")"
    printf 'Archive         : %s TOC entries, extension unaccent in dump: %s\n' "${entries}" "${has_unaccent}"
    printf 'Target database : %s on %s:%s as %s\n' "${PGDATABASE}" "${PGHOST}" "${PGPORT}" "${PGUSER}"
    printf 'Target today    : %s table(s) in schema public WILL BE DROPPED AND REPLACED\n' "${target_tables}"
    printf 'Method          : pg_restore --clean --if-exists --single-transaction --no-owner --no-acl\n'
    printf '                  (all-or-nothing: on any error the target is left untouched)\n'
    printf 'Messenger queue : %s\n' "${queue_plan}"
    printf 'Before you run  : stop the backend and the Messenger worker (open connections block DROP).\n'
    printf 'After           : check extension unaccent, run doctrine:migrations:status, restart services.\n'
    printf '==================================\n\n'
  } >&2

  if (( dry_run == 1 )); then
    log "dry-run: nothing was changed"
    (( reachable == 1 )) || log "WARNING: target database is not reachable with RESTORE_DATABASE_URL"
    return 0
  fi
  (( reachable == 1 )) || die "target database is not reachable; create it first (createdb) and check RESTORE_DATABASE_URL"

  # --- 6. Explicit confirmation ---
  if (( force == 1 )); then
    [[ ${RESTORE_CONFIRM_DB:-} == "${PGDATABASE}" ]] \
      || die "--force requires RESTORE_CONFIRM_DB=${PGDATABASE} (typed explicitly)"
  else
    [[ -t 0 ]] || die "no terminal for confirmation; use --force with RESTORE_CONFIRM_DB for non-interactive runs"
    local answer
    printf 'This will OVERWRITE database "%s" on %s. Type the database name to confirm: ' "${PGDATABASE}" "${PGHOST}" >&2
    read -r answer
    [[ ${answer} == "${PGDATABASE}" ]] || die "confirmation did not match; aborting, nothing changed"
  fi

  # --- 7. Restore ---
  log "restoring into '${PGDATABASE}'"
  pg_restore --clean --if-exists --single-transaction --no-owner --no-acl \
    --no-password --dbname="${PGDATABASE}" ${list_args[@]+"${list_args[@]}"} "${dump}" \
    || die "pg_restore failed; the transaction was rolled back, target unchanged"

  # --- 8. Post-restore checks ---
  local ext tables
  ext=$(psql --no-psqlrc --no-password -Atq -c "SELECT count(*) FROM pg_extension WHERE extname = 'unaccent'")
  if [[ ${ext} != 1 ]]; then
    die "restore finished but extension unaccent is missing; run: CREATE EXTENSION IF NOT EXISTS unaccent; (needs sufficient privileges)"
  fi
  tables=$(psql --no-psqlrc --no-password -Atq \
    -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
  log "restore OK: ${tables} table(s) in public, extension unaccent present"
  log "next: run 'doctrine:migrations:status' and restart the backend and worker"
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
  main "$@"
fi
