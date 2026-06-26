# deskhand

**deskhand — isolated, test-passing Laravel environments per worktree, for running parallel AI coding agents.**

Running several AI coding agents against one checkout causes collisions: agents overwrite each other's files, corrupt a shared database (fatal for event-sourcing projection rebuilds), and read each other's half-finished work. Git worktrees isolate **code** — but not the runtime environment. A fresh worktree has no `.env`, no `APP_KEY`, no `vendor/`, no database, no storage link, and every dev server fights over the same ports.

deskhand closes that gap. `deskhand up <branch>` turns a bare worktree into a fully provisioned, **isolated, test-passing** Laravel environment — its own database, ports, env, and a fresh app key — and verifies it by running your Pest suite. `deskhand down` tears it all down, dropping **only** what deskhand created.

> Each agent gets its own **desk** (an isolated worktree environment). deskhand is the **hand** that sets it up and keeps it working.

## Status

🚧 Early development. The full specification lives in [`docs/implementation.md`](./docs/implementation.md).

## Requirements

- PHP **8.3+** (deskhand is a global CLI; this is the PHP on *your* machine, not your project's pinned version)
- Git, Composer
- macOS or Linux (Windows is not supported)

## Installation

> Available at v1.

```bash
# Global Composer tool
composer global require albertoarena/deskhand

# Or download the standalone PHAR from the latest GitHub Release
```

## Quickstart

```bash
# Provision an isolated, verified environment for a branch
deskhand up feature/billing

# ... point an agent at .claude/worktrees/feature-billing and let it work ...

# See what's running
deskhand list

# Tear it down (drops only what deskhand created)
deskhand down feature/billing
```

## Commands

| Command | What it does |
|---|---|
| `deskhand up <branch>` | Create + provision + verify an isolated worktree environment |
| `deskhand down <branch\|slug>` | Tear down, dropping only deskhand-created resources |
| `deskhand list` | List all deskhand-managed worktrees |
| `deskhand status [<branch\|slug>]` | Health-check managed worktrees |
| `deskhand skill:install` | Install the Claude Code skill into `.claude/skills` (`--global` for the user) |

Key flags on `up`: `--path`, `--db=sqlite|mysql`, `--shared-db`, `--url`, `--no-envaudit`, `--no-redis-isolation`, `--no-verify`. Full reference in the docs.

## Claude Code skill

deskhand ships a [Claude Code skill](./skill/SKILL.md) so agents know when and how
to use it — and the safety rules they must respect. Install it into a project (or
for your user with `--global`) so Claude Code discovers it:

```bash
deskhand skill:install            # → .claude/skills/deskhand/SKILL.md
deskhand skill:install --global   # → ~/.claude/skills/deskhand/SKILL.md
```

## How it isolates

Full database isolation by default (SQLite per worktree; `--db=mysql` when needed), deterministic slug-derived ports, a copied per-worktree `.env` with a fresh `APP_KEY`, conditional Redis namespacing, and per-worktree dependencies. See [`docs/isolation-model.md`](./docs/isolation-model.md).

## Safety

deskhand creates and destroys databases, so trust matters. The cardinal rule: **it never drops a database it did not create** — teardown is driven entirely by a registry of what deskhand made, never by guessing names. See [`docs/safety-model.md`](./docs/safety-model.md).

## Configuration

Zero-config works for a vanilla Laravel app. A committed `deskhand.yaml` covers per-project needs — including custom migrate/seed/test commands (e.g. a project's own `php artisan migrations`), port ranges, seeding, URL strategy (`serve` / Herd / Valet / custom), and post-up hooks. See the configuration reference in the docs.

## Acceptance testing

deskhand has been exercised end-to-end against a real Laravel app: `up` provisions
and verifies an isolated worktree (real composer, npm, artisan, migrate and a green
suite), and three coding agents then worked the **same codebase in parallel** —
each in its own worktree with a distinct database, ports and `.env` — with zero
collisions, before `down` removed only what deskhand created.

You can replicate this two ways:

- **Scripted (routine, ~free).** Scaffolds a fresh Laravel app, provisions N
  isolated worktrees, runs a deterministic workload **concurrently** in each,
  asserts isolation (distinct databases/ports, base untouched) and tears them down:

  ```bash
  scripts/acceptance/parallel-worktrees.sh        # 3 workers (default)
  WORKERS=5 scripts/acceptance/parallel-worktrees.sh
  ```

  Prints `ACCEPTANCE: PASS`. Requires `composer` and `php` on `PATH`.

- **With real AI agents (milestone).** Follow
  [`docs/acceptance/ai-agents.md`](./docs/acceptance/ai-agents.md) to drive actual
  coding agents in parallel worktrees — the real use case.

There is also a gated end-to-end test: `DESKHAND_TEST_LARAVEL=1 vendor/bin/pest`
runs the full round-trip against a scaffolded Laravel app (skipped by default).

## Documentation

Full docs: **https://albertoarena.github.io/deskhand**

## Contributing

See [`CONTRIBUTING.md`](./CONTRIBUTING.md). TDD is required; the [safety invariants](./docs/safety-model.md) are non-negotiable.

## License

[MIT](./LICENSE) © 2026 Alberto Arena
