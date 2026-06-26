---
name: deskhand
description: >-
  Provision an isolated, test-passing Laravel environment for a git branch so
  multiple coding agents (or humans) can work the same repo in parallel without
  colliding on files, databases, or ports. Use when you need to start work on a
  branch in its own worktree, list or health-check existing deskhand
  environments, or tear one down. Each environment gets its own git worktree,
  database, deterministic ports, and copied .env with a fresh APP_KEY.
---

# deskhand

`deskhand` is a stack-agnostic PHP CLI that turns a bare git branch into a fully
runnable, **test-passing**, **isolated** Laravel environment in its own git
worktree. It exists so several agents can work one codebase at once without
stepping on each other.

Each environment is isolated: its own worktree directory, its own database
(SQLite per worktree by default), deterministic slug-derived ports, a **copied**
`.env` (never symlinked) with a fresh `APP_KEY`, and its own dependencies.

## When to use it

- You are about to do work on a branch and want a clean, verified, isolated
  environment for it — especially when other agents are working the same repo.
- You need to see what deskhand environments exist (`list`) or whether one is
  healthy (`status`).
- You are done with a branch's environment and want to remove **only** what
  deskhand created (`down`).

Do **not** use it for non-Laravel projects (v1 is Laravel-only), and do not hand-
roll worktree/database isolation yourself — that is exactly what deskhand owns.

## Prerequisites

- Run it from **inside the target Laravel git repository**. deskhand resolves the
  repository from the current directory; running it elsewhere targets the wrong
  repo.
- PHP 8.3+, Composer, and a git repository. For `--db=mysql`, a reachable MySQL
  server with credentials in the project `.env`.
- Installed either as a global Composer tool (`deskhand`) or as the standalone
  PHAR. Invocations below use `deskhand`.

## Commands

### `deskhand up <branch>`
Provision (or repair) an isolated, verified environment for `<branch>`. Attaches
to the branch if it exists, creates it otherwise. Re-running is idempotent.

- `--path=<dir>` — worktree location (default `.claude/worktrees/<slug>`).
- `--db=<sqlite|mysql>` — database engine (default `sqlite`).
- `--shared-db` — use the base project database **read-only** (skips DB creation,
  migrate, and seed). For read-only inspection sessions.
- `--url=<value>` — override the reported URL (supports `{slug}`/`{port}`).
- `--no-envaudit` — skip the envaudit gate.
- `--no-redis-isolation` — skip Redis prefix/index injection.
- `--no-verify` — provision without running the test suite.

On success it prints the slug, branch, worktree path, database, ports, URL, and
health. A failing test suite fails `up` with exit code 6.

### `deskhand down <branch|slug>`
Tear down an environment, removing **only** what deskhand recorded creating.

- `--keep-branch` — remove the worktree but keep the git branch.
- `--force` — proceed without interactive confirmation. **Required for agents/CI**
  (no TTY): without it, `down` refuses rather than acting unconfirmed.

### `deskhand list`
List all deskhand-managed worktrees (slug, branch, path, database, ports, URL,
created). Add `--json` for machine-readable output.

### `deskhand status [<branch|slug>]`
Health check (worktree present, `.env` present, database reachable, ports in
use). No argument summarizes all; an argument inspects one. `--json` supported.

## Safety rules an agent must respect

1. **Never bypass `down`.** To remove an environment, always use `deskhand down`.
   Do not manually `DROP DATABASE`, delete worktree directories, or `git worktree
   remove` deskhand's worktrees yourself — `down` drops only registry-recorded
   resources, which is the guarantee that nothing else is destroyed.
2. **Never run destructive database operations directly** against deskhand or the
   base database. deskhand owns creation and teardown.
3. **Stay inside your own worktree.** Do not edit files in sibling worktrees or
   the base checkout.
4. **Use `--force` for `down` when non-interactive.** Agents have no TTY.
5. **Never commit** `.claude/deskhand/` or `.claude/worktrees/` — they are local
   machine state (deskhand gitignores them).

## Worked example

```bash
# From inside the Laravel app repository:
deskhand up feature/billing
#  → worktree at .claude/worktrees/feature-billing, isolated SQLite DB,
#    deterministic ports, migrated, suite green.

# Do the work inside that worktree:
cd .claude/worktrees/feature-billing
# ...edit code, run `php artisan test`, etc...

# Check health at any time (from the repo root):
deskhand status feature/billing

# When finished, tear down (agents must pass --force):
deskhand down feature/billing --force
```

## Configuration

Zero-config works for a vanilla Laravel app. A committed `deskhand.yaml` at the
repo root customizes behaviour: `db_connection`, `serve_port_range`,
`vite_port_range`, `frontend_install`, `js_package_manager`, `seed`,
`url_strategy`/`url_template`/`url_domain`, `migrate_command`, `seed_command`,
`test_command`, `post_up_hooks`, and `redis_isolation`. Configured commands run
verbatim inside the worktree with the `DESKHAND_*` worktree facts in the
environment.
