#!/usr/bin/env bash
#
# Tier-2 skill dry-run harness (see docs/acceptance/skill-testing.md).
#
# Automates everything *around* the agent session: it puts `deskhand` on PATH,
# scaffolds a Laravel app, installs the skill into it, and then hands off to you
# (or a headless `claude -p` run) to drive the actual session. Afterwards it can
# verify the on-disk mechanics and tear everything down.
#
# Usage:
#   scripts/acceptance/skill-dryrun.sh setup            # prepare app + skill, print the prompt
#   scripts/acceptance/skill-dryrun.sh setup --headless # also run `claude -p` automatically
#   scripts/acceptance/skill-dryrun.sh verify           # show deskhand list/status for the app
#   scripts/acceptance/skill-dryrun.sh teardown         # down worktrees, remove app + PATH link
#
# Environment:
#   PHP           php binary (default: php)
#   DESKHAND_BIN  path to bin/deskhand (default: resolved from this script)
#   DRYRUN_DIR    scratch directory (default: $TMPDIR/deskhand-skill-dryrun)
#   PROMPT        the natural-language prompt to use (does NOT name deskhand)
#
set -euo pipefail

ACTION="${1:-setup}"
HEADLESS=0
[ "${2:-}" = "--headless" ] && HEADLESS=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP="${PHP:-php}"
DESKHAND_BIN="${DESKHAND_BIN:-$(cd "$SCRIPT_DIR/../.." && pwd)/bin/deskhand}"
ROOT="${DRYRUN_DIR:-${TMPDIR:-/tmp}/deskhand-skill-dryrun}"
APP="$ROOT/app"
STATE="$ROOT/.path-link"
PROMPT="${PROMPT:-Start work on branch feature/reports in an isolated, test-passing environment that will not collide with other agents working this repo, then run its tests.}"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m! %s\033[0m\n' "$1"; }
die()  { printf '\033[1;31mERROR: %s\033[0m\n' "$1" >&2; exit 1; }

ensure_on_path() {
  if command -v deskhand >/dev/null 2>&1; then
    say "deskhand already on PATH: $(command -v deskhand)"
    return
  fi
  IFS=: read -ra dirs <<< "$PATH"
  for d in "${dirs[@]}"; do
    if [ -n "$d" ] && [ -d "$d" ] && [ -w "$d" ]; then
      ln -sf "$DESKHAND_BIN" "$d/deskhand"
      mkdir -p "$ROOT"
      echo "$d/deskhand" > "$STATE"
      say "Linked deskhand -> $d/deskhand"
      return
    fi
  done
  warn "No writable directory on PATH. Link it yourself before the session:"
  printf '    ln -s %s <a-dir-on-your-PATH>/deskhand\n' "$DESKHAND_BIN"
}

do_setup() {
  command -v composer >/dev/null || die "composer not found on PATH"
  [ -f "$DESKHAND_BIN" ] || die "deskhand binary not found at $DESKHAND_BIN"

  ensure_on_path

  if [ ! -d "$APP" ]; then
    say "Scaffolding a fresh Laravel app at $APP"
    mkdir -p "$ROOT"
    composer create-project laravel/laravel "$APP" --quiet
    git -C "$APP" init -q -b main
    git -C "$APP" config user.email skill-dryrun@deskhand.test
    git -C "$APP" config user.name "deskhand skill dry-run"
    git -C "$APP" config commit.gpgsign false
    git -C "$APP" add -A
    git -C "$APP" commit -q -m "init laravel"
  else
    say "Reusing existing app at $APP"
  fi

  say "Installing the skill into the project"
  ( cd "$APP" && "$PHP" "$DESKHAND_BIN" skill:install )

  if [ "$HEADLESS" = "1" ]; then
    command -v claude >/dev/null || die "claude CLI not found for --headless"
    say "Running a headless agent session (claude -p)"
    ( cd "$APP" && claude -p "$PROMPT" --verbose ) || warn "claude session exited non-zero"
    do_verify
    return
  fi

  cat <<EOF

$(say "Ready — drive the session yourself")
  1. Open a session in the app directory:
       cd "$APP" && claude
  2. Prompt with INTENT, without naming deskhand, e.g.:
       "$PROMPT"
     Try 2-3 phrasings to gauge how reliably the skill triggers.
  3. Score against the rubric in docs/acceptance/skill-testing.md
     (did the skill trigger? did it use up/down correctly and stay in the worktree?).

  Inspect mechanics:   scripts/acceptance/skill-dryrun.sh verify
  Clean up everything: scripts/acceptance/skill-dryrun.sh teardown
EOF
}

do_verify() {
  [ -d "$APP" ] || die "no scratch app at $APP — run setup first"
  say "deskhand list"
  ( cd "$APP" && "$PHP" "$DESKHAND_BIN" list )
  say "deskhand status"
  ( cd "$APP" && "$PHP" "$DESKHAND_BIN" status )
}

do_teardown() {
  if [ -d "$APP" ]; then
    say "Tearing down any remaining worktrees"
    ( cd "$APP" && "$PHP" "$DESKHAND_BIN" list --json \
      | "$PHP" -r '$d=json_decode(stream_get_contents(STDIN),true)?:[];foreach($d as $r)echo $r["branch"],"\n";' ) \
      | while read -r b; do
          [ -n "$b" ] && ( cd "$APP" && "$PHP" "$DESKHAND_BIN" down "$b" --force ) || true
        done
  fi
  if [ -f "$STATE" ]; then
    link="$(cat "$STATE")"
    [ -L "$link" ] && rm -f "$link" && say "Removed PATH link $link"
  fi
  rm -rf "$ROOT"
  say "Removed scratch directory $ROOT"
}

case "$ACTION" in
  setup)    do_setup ;;
  verify)   do_verify ;;
  teardown) do_teardown ;;
  *) die "unknown action '$ACTION' (use: setup | verify | teardown)" ;;
esac
