# deskhand — Isolation Model

This document explains *what* gets isolated between worktrees and *how*. The goal: several agents running in parallel behave like several independent developers working in separate branches — none stepping on another's files, database, ports, or environment.

## What a git worktree gives you (and what it doesn't)

A git worktree is a separate working directory checked out to a different branch, sharing one underlying `.git` object store. That gives you **code isolation** for free: each agent edits its own files on its own branch, and conflicts move to merge time where normal git tooling handles them.

What a worktree does **not** give you:

- `.env` (gitignored) — absent in a fresh worktree
- `APP_KEY` — would be shared if you copied `.env` naively
- `vendor/` and `node_modules/` — not created
- a database — shared with the base checkout unless you isolate it
- storage symlinks — missing
- ports — every dev server wants the same ones

deskhand provisions each of these per worktree. The sections below cover each isolation dimension.

## Database isolation

This is the dimension that matters most, especially for event sourcing.

**Default: full isolation.** Each worktree gets its own database, plus its own numbered databases for parallel Pest runs. Two agents rebuilding projections, running migrations, or appending events never touch each other's data.

Why full isolation is the default for event-sourced apps specifically: two agents appending events to a *shared* event store would interleave streams and corrupt each other's aggregates; two agents rebuilding read models against a shared database would clobber each other's projections. Isolation makes each desk a clean, private world.

**`--shared-db`** exists for genuinely read-only sessions (e.g. an agent that only investigates the codebase). It points the worktree at the base database and skips DB creation. Use it only when no writes will happen.

**Engines.** SQLite is the default — a database is just a file, so creation and teardown are instant and isolation is perfect. `--db=mysql` is available for projects whose behaviour depends on MySQL specifics (certain JSON, locking, or full-text behaviour); deskhand then creates uniquely-named MySQL databases per worktree. (MySQL only — not MariaDB.)

## Environment isolation

- The base `.env` is **copied** into each worktree, never symlinked, so edits in one desk don't propagate to others.
- A **fresh `APP_KEY`** is generated per worktree — sessions and encryption are never shared across desks.
- Per-worktree overrides are injected: the database name/connection, an `APP_NAME` tag (`<app> [<slug>]`) so environments are distinguishable in logs/mail/browser, the assigned ports, and (conditionally) Redis namespacing.
- `.env.testing` always forces safe drivers (array cache/session, sync queue), so the suite is hermetic regardless of what the runtime `.env` uses.

## Port isolation

Every dev server defaults to the same ports, so two running worktrees would collide. deskhand assigns each worktree **deterministic, slug-derived ports** — a hash of the slug mapped into configured ranges — so the same branch always gets the same ports (no scan, stable across runs). If a derived port is occupied by a foreign process, deskhand surfaces it rather than silently reassigning.

## Redis isolation (conditional)

Most test suites mock Redis, so isolation is only relevant for the running app, not the suite. deskhand therefore makes Redis isolation **conditional**:

- It activates only if real Redis use is detected in `.env` (redis cache/queue/session drivers or `REDIS_*` present).
- When active, it injects a per-slug key **prefix** (the primary, effectively unlimited mechanism) and assigns a logical **DB index** derived deterministically as `hash(slug) % 16` (a bonus wall for low worktree counts). Because only 16 indices exist, index collisions are **tolerated, never an error** — the prefix already guarantees isolation; deskhand does not scan for a free index, keeping the index stable per branch like ports.
- `.env.testing` forces safe drivers regardless, so tests never depend on shared Redis.
- `--no-redis-isolation` opts out.

Process-level isolation (a separate Redis instance per worktree) is intentionally **not** done — it's overkill for local dev. The escape hatch for prefix-hostile setups (apps using `FLUSHALL`/`KEYS *`/clean-DB-assuming Lua) is documented, not automated.

## Dependency isolation

`composer install` runs in each worktree (its own `vendor/`). When a frontend is detected, a JS install runs too, giving each worktree its own `node_modules`. The package manager is chosen from the committed lockfile — `yarn.lock` → yarn, `package-lock.json` → npm (npm fallback when ambiguous, overridable via `js_package_manager`) — using the lockfile-respecting install (`npm ci` / `yarn --immutable`/`--frozen-lockfile`) when a lockfile exists, and the plain install (`npm install` / `yarn install`) otherwise. deskhand does **not** symlink `node_modules` across worktrees by default — concurrent installs can corrupt a shared directory, and branches may differ in dependencies. `--prefer-offline` keeps installs fast by reusing the shared package-manager cache without assuming lockfile parity.

## What isolation does *not* cover

Isolation is the *floor* for parallel agent work, not the whole story. deskhand gives each agent a clean, private substrate; it does **not** decide which agent works on what. To avoid merge pain, partition work by file ownership up front (one agent owns the domain layer, another the Filament panel, another the HTTP layer) and record that map in `AGENTS.md`/`CLAUDE.md`. That coordination layer is deliberately outside deskhand's scope.
