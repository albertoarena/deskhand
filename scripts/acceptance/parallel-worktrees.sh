#!/usr/bin/env bash
#
# deskhand acceptance harness — parallel worktree isolation (no AI).
#
# Scaffolds a fresh Laravel app, provisions N isolated worktrees with
# `deskhand up`, runs a deterministic workload (add a route + a test, then run
# the suite) CONCURRENTLY in each worktree, asserts isolation, and tears them
# all down with `deskhand down`. Re-run this after any important change to prove
# the parallel-isolation guarantee still holds.
#
# Usage:
#   scripts/acceptance/parallel-worktrees.sh
#
# Environment:
#   WORKERS       number of parallel worktrees (default 3)
#   PHP           php binary (default: php)
#   DESKHAND_BIN  path to bin/deskhand (default: resolved from this script)
#   KEEP          set to 1 to keep the scratch app for inspection
#
set -euo pipefail

WORKERS="${WORKERS:-3}"
PHP="${PHP:-php}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESKHAND_BIN="${DESKHAND_BIN:-$(cd "$SCRIPT_DIR/../.." && pwd)/bin/deskhand}"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31mACCEPTANCE: FAIL — %s\033[0m\n' "$1" >&2; exit 1; }

command -v composer >/dev/null || fail "composer not found on PATH"
[ -f "$DESKHAND_BIN" ] || fail "deskhand binary not found at $DESKHAND_BIN"

WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/deskhand-acceptance.XXXXXX")"
APP="$WORKDIR/app"
cleanup() { cd / 2>/dev/null || true; [ "${KEEP:-0}" = "1" ] || rm -rf "$WORKDIR"; }
trap cleanup EXIT

say "Scaffolding a fresh Laravel app"
composer create-project laravel/laravel "$APP" --quiet
git -C "$APP" init -q -b main
git -C "$APP" config user.email acceptance@deskhand.test
git -C "$APP" config user.name "deskhand acceptance"
git -C "$APP" config commit.gpgsign false
git -C "$APP" add -A
git -C "$APP" commit -q -m "init laravel"

# deskhand resolves the repo from the current directory, so run it from the app.
cd "$APP"

branches=()
for i in $(seq 1 "$WORKERS"); do branches+=("worker/$i"); done

say "Provisioning $WORKERS isolated worktrees"
for b in "${branches[@]}"; do "$PHP" "$DESKHAND_BIN" up "$b"; done

say "Running a deterministic workload concurrently in each worktree"
pids=()
for i in $(seq 1 "$WORKERS"); do
  wt="$APP/.claude/worktrees/worker-$i"
  (
    printf "\nRoute::get('/worker-%s/ping', fn () => response()->json(['worker' => %s, 'ok' => true]));\n" "$i" "$i" >> "$wt/routes/web.php"
    cat > "$wt/tests/Feature/Worker${i}Test.php" <<PHP
<?php

namespace Tests\\Feature;

use Tests\\TestCase;

class Worker${i}Test extends TestCase
{
    public function test_ping(): void
    {
        \$this->get('/worker-${i}/ping')->assertOk()->assertJson(['worker' => ${i}]);
    }
}
PHP
    cd "$wt" && "$PHP" artisan test --filter="Worker${i}Test" >/dev/null
  ) &
  pids+=($!)
done

workload_failed=0
for p in "${pids[@]}"; do wait "$p" || workload_failed=1; done
[ "$workload_failed" -eq 0 ] || fail "a concurrent worktree workload failed"
say "All $WORKERS concurrent suites passed"

say "Asserting isolation"
# Base app must not have seen any worker's changes.
grep -q "worker-" "$APP/routes/web.php" && fail "base app routes were modified"

# Each worktree must point at its own, distinct database.
unique_dbs="$(for i in $(seq 1 "$WORKERS"); do
  grep '^DB_DATABASE=' "$APP/.claude/worktrees/worker-$i/.env"
done | sort -u | wc -l | tr -d ' ')"
[ "$unique_dbs" -eq "$WORKERS" ] || fail "worktrees do not have distinct databases ($unique_dbs/$WORKERS)"

# Each worktree must carry only its own ping route.
for i in $(seq 1 "$WORKERS"); do
  count="$(grep -c "/worker-.*/ping" "$APP/.claude/worktrees/worker-$i/routes/web.php" || true)"
  [ "$count" -eq 1 ] || fail "worktree worker-$i has $count ping routes (expected 1)"
done

# Serve ports must be distinct.
unique_ports="$("$PHP" "$DESKHAND_BIN" list --json | "$PHP" -r '$d=json_decode(stream_get_contents(STDIN),true);$p=array_map(fn($r)=>$r["ports"]["serve"],$d);echo count(array_unique($p));')"
[ "$unique_ports" -eq "$WORKERS" ] || fail "serve ports are not distinct ($unique_ports/$WORKERS)"

say "Tearing down all worktrees"
for b in "${branches[@]}"; do "$PHP" "$DESKHAND_BIN" down "$b" --force; done

remaining="$("$PHP" "$DESKHAND_BIN" list --json | "$PHP" -r 'echo count(json_decode(stream_get_contents(STDIN),true));')"
[ "$remaining" -eq 0 ] || fail "registry not empty after teardown ($remaining records)"
[ -z "$(ls -A "$APP/.claude/worktrees" 2>/dev/null || true)" ] || fail "worktree directories remain after teardown"

printf '\n\033[1;32mACCEPTANCE: PASS — %s parallel worktrees isolated, verified and torn down.\033[0m\n' "$WORKERS"
