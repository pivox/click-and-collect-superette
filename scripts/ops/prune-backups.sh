#!/usr/bin/env bash
# prune-backups.sh - grandfather-father-son retention for backups in BACKUP_DIR.
#
# Only files named <prefix>-(postgres|uploads)-<YYYYMMDDTHHMMSSZ>.<ext> (and their
# .sha256 sidecars, plus stale .partial files) are ever considered. Anything else in the
# directory is left alone. Every deletion is printed BEFORE it happens.
#
# Policy (union of the three rules, counted per backup kind):
#   - newest backup of each of the last KEEP_DAILY days that have a backup
#   - newest backup of each of the last KEEP_WEEKLY ISO weeks that have a backup
#   - newest backup of each of the last KEEP_MONTHLY months that have a backup
#   - the single newest backup of a kind is ALWAYS kept
# Requires GNU date (coreutils).
set -euo pipefail

SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"

usage() {
  cat >&2 <<USAGE
Usage: ${SCRIPT_NAME} [--dry-run] [-h|--help]

  --dry-run   Print what would be kept/deleted, delete nothing.

Required environment:
  BACKUP_DIR            Directory holding the backups.

Optional environment:
  BACKUP_PREFIX         Only handle files with this prefix (default: clickcollect).
  KEEP_DAILY            Days kept (default: 7).
  KEEP_WEEKLY           Weeks kept (default: 4).
  KEEP_MONTHLY          Months kept (default: 6).
  BACKUP_MAX_AGE_HOURS  Warn if the newest backup of a kind is older (default: 30).

Note: this prunes the LOCAL directory only. Apply retention to the off-site bucket
with its own lifecycle rules.
USAGE
}

log() { printf '%s [%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${SCRIPT_NAME}" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

is_uint() { [[ $1 =~ ^[0-9]+$ ]]; }

main() {
  local dry_run=0
  while (( $# > 0 )); do
    case "$1" in
      -h | --help) usage; exit 0 ;;
      --dry-run) dry_run=1 ;;
      *) usage; die "unknown argument: $1" ;;
    esac
    shift
  done

  [[ -n ${BACKUP_DIR:-} ]] || { usage; die "BACKUP_DIR is not set"; }
  [[ -d ${BACKUP_DIR} ]] || die "BACKUP_DIR is not a directory: ${BACKUP_DIR}"
  local prefix=${BACKUP_PREFIX:-clickcollect}
  local keep_daily=${KEEP_DAILY:-7} keep_weekly=${KEEP_WEEKLY:-4} keep_monthly=${KEEP_MONTHLY:-6}
  local max_age=${BACKUP_MAX_AGE_HOURS:-30}
  [[ ${prefix} =~ ^[A-Za-z0-9_.]+$ ]] || die "BACKUP_PREFIX may only contain [A-Za-z0-9_.]"
  # Spelled out as an if/then rather than an && chain so the intent is explicit
  # (shellcheck SC2015 flags the chained form even when it behaves correctly).
  if ! is_uint "${keep_daily}" || ! is_uint "${keep_weekly}" \
    || ! is_uint "${keep_monthly}" || ! is_uint "${max_age}"; then
    die "KEEP_DAILY, KEEP_WEEKLY, KEEP_MONTHLY and BACKUP_MAX_AGE_HOURS must be non-negative integers"
  fi
  date -u -d '2026-01-05' +%G-%V >/dev/null 2>&1 || die "GNU date is required"

  log "policy: daily=${keep_daily} weekly=${keep_weekly} monthly=${keep_monthly} dir=${BACKUP_DIR} dry_run=${dry_run}"

  local kind total_deleted=0
  for kind in postgres uploads; do
    # Newest first; the timestamp is lexically sortable.
    local -a files=()
    local f
    while IFS= read -r f; do
      files+=("${f}")
    done < <(find "${BACKUP_DIR}" -maxdepth 1 -type f \
      -regextype posix-extended \
      -regex ".*/${prefix}-${kind}-[0-9]{8}T[0-9]{6}Z\\.(dump|tar)\\.age" -printf '%f\n' | sort -r)

    if (( ${#files[@]} == 0 )); then
      log "${kind}: no backup found"
      continue
    fi

    local -A kept_day=() kept_week=() kept_month=()
    local -A keep=()
    local n_day=0 n_week=0 n_month=0 name stamp day month week
    for name in "${files[@]}"; do
      stamp=${name#"${prefix}-${kind}-"}
      stamp=${stamp%%.*}
      day=${stamp:0:8}
      month=${stamp:0:6}
      week=$(date -u -d "${day:0:4}-${day:4:2}-${day:6:2}" +%G-%V)
      if [[ -z ${kept_day[${day}]:-} ]] && (( n_day < keep_daily )); then
        kept_day[${day}]=1; n_day=$((n_day + 1)); keep[${name}]="daily ${day}"
      fi
      if [[ -z ${kept_week[${week}]:-} ]] && (( n_week < keep_weekly )); then
        kept_week[${week}]=1; n_week=$((n_week + 1)); keep[${name}]="${keep[${name}]:+${keep[${name}]}, }weekly ${week}"
      fi
      if [[ -z ${kept_month[${month}]:-} ]] && (( n_month < keep_monthly )); then
        kept_month[${month}]=1; n_month=$((n_month + 1)); keep[${name}]="${keep[${name}]:+${keep[${name}]}, }monthly ${month}"
      fi
    done
    # Safety net: the newest backup is always kept, whatever the policy says.
    [[ -n ${keep[${files[0]}]:-} ]] || keep[${files[0]}]="newest (safety)"

    # Freshness warning: a pruned-but-stale directory usually means broken backups.
    local newest_stamp=${files[0]#"${prefix}-${kind}-"}
    newest_stamp=${newest_stamp%%.*}
    local newest_epoch now_epoch
    newest_epoch=$(date -u -d "${newest_stamp:0:4}-${newest_stamp:4:2}-${newest_stamp:6:2} ${newest_stamp:9:2}:${newest_stamp:11:2}:${newest_stamp:13:2} UTC" +%s)
    now_epoch=$(date -u +%s)
    if (( (now_epoch - newest_epoch) / 3600 > max_age )); then
      log "WARNING: newest ${kind} backup is older than ${max_age}h (${files[0]}); are backups still running?"
    fi

    for name in "${files[@]}"; do
      if [[ -n ${keep[${name}]:-} ]]; then
        log "KEEP   ${name} (${keep[${name}]})"
      else
        log "DELETE ${name}"
        if (( dry_run == 0 )); then
          rm -f -- "${BACKUP_DIR}/${name}" "${BACKUP_DIR}/${name}.sha256"
        fi
        total_deleted=$((total_deleted + 1))
      fi
    done
  done

  # Interrupted runs leave *.partial files; remove those older than one day.
  local stale
  while IFS= read -r stale; do
    log "DELETE ${stale} (stale partial file)"
    if (( dry_run == 0 )); then rm -f -- "${BACKUP_DIR}/${stale}"; fi
    total_deleted=$((total_deleted + 1))
  done < <(find "${BACKUP_DIR}" -maxdepth 1 -type f -regextype posix-extended \
    -regex ".*/${prefix}-(postgres|uploads)-[0-9]{8}T[0-9]{6}Z\\.(dump|tar)\\.age\\.partial" -mtime +0 -printf '%f\n' | sort)

  if (( dry_run == 1 )); then
    log "dry-run: ${total_deleted} file(s) would be deleted"
  else
    log "done: ${total_deleted} file(s) deleted"
  fi
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
  main "$@"
fi
