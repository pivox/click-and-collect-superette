#!/usr/bin/env bash
# backup-uploads.sh - encrypted full archive of the product image upload directory.
#
# Output: ${BACKUP_DIR}/${BACKUP_PREFIX}-uploads-<UTC timestamp>.tar.age (+ .sha256 sidecar)
# The tar is not gzip-compressed on purpose: the content is WebP/JPEG, already compressed.
# The absolute path of the final file is the ONLY thing written to stdout.
set -euo pipefail

SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
  cat >&2 <<USAGE
Usage: ${SCRIPT_NAME} [-h|--help]

Archives the product image upload directory (original + WebP variants + JPEG
fallback), encrypts the stream with age and stores it in BACKUP_DIR.

Required environment:
  BACKUP_DIR                   Destination directory for backups.
  BACKUP_UPLOADS_DIR           Directory to archive (the Docker volume mount point of
                               apps/backend/public/uploads/products).
  BACKUP_AGE_RECIPIENT         age public key (age1...) OR
  BACKUP_AGE_RECIPIENTS_FILE   file with one age/ssh recipient per line.

Optional environment:
  BACKUP_PREFIX                File name prefix (default: clickcollect).
  BACKUP_MIN_FILES             Refuse to back up fewer files than this (default: 1);
                               protects against archiving an unmounted, empty volume.
USAGE
}

log() { printf '%s [%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${SCRIPT_NAME}" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

require_cmd() {
  local c
  for c in "$@"; do
    command -v "${c}" >/dev/null 2>&1 || die "required command not found: ${c}"
  done
}

PARTIAL=""
cleanup() {
  [[ -n ${PARTIAL} ]] && rm -f -- "${PARTIAL}"
  return 0
}

main() {
  case "${1:-}" in
    -h | --help) usage; exit 0 ;;
    "") ;;
    *) usage; die "unexpected argument: $1" ;;
  esac

  [[ -n ${BACKUP_DIR:-} ]] || { usage; die "BACKUP_DIR is not set"; }
  [[ -n ${BACKUP_UPLOADS_DIR:-} ]] || { usage; die "BACKUP_UPLOADS_DIR is not set"; }
  [[ -d ${BACKUP_UPLOADS_DIR} ]] || die "BACKUP_UPLOADS_DIR is not a directory: ${BACKUP_UPLOADS_DIR}"
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
  require_cmd tar age sha256sum find stat

  local prefix=${BACKUP_PREFIX:-clickcollect}
  local min_files=${BACKUP_MIN_FILES:-1}
  [[ ${prefix} =~ ^[A-Za-z0-9_.]+$ ]] || die "BACKUP_PREFIX may only contain [A-Za-z0-9_.]"
  [[ ${min_files} =~ ^[0-9]+$ ]] || die "BACKUP_MIN_FILES must be an integer"

  umask 077
  mkdir -p -- "${BACKUP_DIR}"
  chmod 700 -- "${BACKUP_DIR}" 2>/dev/null || true

  if command -v flock >/dev/null 2>&1; then
    exec 9>"${BACKUP_DIR}/.${SCRIPT_NAME}.lock"
    flock -n 9 || die "another ${SCRIPT_NAME} run is in progress"
  fi
  trap cleanup EXIT

  local file_count
  file_count=$(find "${BACKUP_UPLOADS_DIR}" -type f | wc -l)
  (( file_count >= min_files )) \
    || die "only ${file_count} file(s) in ${BACKUP_UPLOADS_DIR} (< ${min_files}); is the volume mounted?"

  local ts final
  ts=$(date -u +%Y%m%dT%H%M%SZ)
  final="${BACKUP_DIR}/${prefix}-uploads-${ts}.tar.age"
  if [[ -e ${final} ]]; then
    log "backup already exists, nothing to do: ${final}"
    printf '%s\n' "${final}"
    return 0
  fi
  PARTIAL="${final}.partial"

  local -a age_args=()
  if [[ -n ${BACKUP_AGE_RECIPIENT:-} ]]; then age_args+=(-r "${BACKUP_AGE_RECIPIENT}"); fi
  if [[ -n ${BACKUP_AGE_RECIPIENTS_FILE:-} ]]; then age_args+=(-R "${BACKUP_AGE_RECIPIENTS_FILE}"); fi

  log "archiving ${BACKUP_UPLOADS_DIR} (${file_count} files)"
  # tar exit code 1 means "file changed as we read it" (an upload happened during the
  # run): the archive is usable, so it is tolerated with a warning. Code >= 2 is fatal.
  # PIPESTATUS is copied immediately: any later command would reset it.
  local -a rcs
  set +e
  tar --create --file=- --directory="${BACKUP_UPLOADS_DIR}" . | age "${age_args[@]}" -o "${PARTIAL}"
  rcs=("${PIPESTATUS[@]}")
  set -e
  local tar_rc=${rcs[0]} age_rc=${rcs[1]}
  (( age_rc == 0 )) || die "age encryption failed (exit ${age_rc})"
  if (( tar_rc == 1 )); then
    log "WARNING: some files changed while being archived (tar exit 1); archive kept"
  elif (( tar_rc != 0 )); then
    die "tar failed (exit ${tar_rc})"
  fi

  [[ -s ${PARTIAL} ]] || die "encrypted file is empty"
  [[ $(head -c 21 "${PARTIAL}") == "age-encryption.org/v1" ]] || die "encrypted file has no age header"

  mv -- "${PARTIAL}" "${final}"
  PARTIAL=""
  (cd -- "${BACKUP_DIR}" && sha256sum -- "$(basename "${final}")" >"$(basename "${final}").sha256")

  log "backup OK: ${final} ($(stat -c %s -- "${final}") bytes encrypted, ${file_count} files)"
  printf '%s\n' "${final}"
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
  main "$@"
fi
