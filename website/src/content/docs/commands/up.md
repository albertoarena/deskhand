---
title: deskhand up
description: Provision a fully runnable, verified, isolated environment for a branch.
---

```bash
deskhand up <branch> [options]
```

Provision a fully runnable, verified, isolated environment for `<branch>`.
If the branch exists, deskhand attaches to it; if not, it creates it.

## Argument

| Argument | Description |
|---|---|
| `branch` | **Required.** The git branch to provision a worktree for. |

## Flags

| Flag | Description |
|---|---|
| `--path=<dir>` | Worktree location. Default: `.claude/worktrees/<slug>` relative to the repo root. |
| `--db=<engine>` | `sqlite` (default) or `mysql`. |
| `--shared-db` | Use the base project's database instead of an isolated one — for **read-only** sessions. Skips DB creation, migrate, and seed, and points the worktree `.env` at the shared DB. |
| `--no-envaudit` | Skip the envaudit gate. |
| `--no-redis-isolation` | Skip Redis prefix/index injection even if Redis is detected. |
| `--no-verify` | Skip the Pest verification step (provision only). |
| `--url=<value>` | Override the reported worktree URL. Accepts a literal URL or a template with `{slug}`/`{port}` (e.g. `--url=https://{slug}.acme.test`). Authoritative; persisted in the registry. |

## What it does

1. **Preflight** — confirm a git repo, load config, detect capabilities, and
   idempotently add deskhand's managed block to `.gitignore`.
2. **Resolve the slug** and derived names (DB names, ports, `APP_NAME` tag).
3. **Create the worktree** (attach or create the branch).
4. **Materialize env** — copy `.env` → worktree `.env` and `.env.testing`,
   injecting per-worktree overrides. `.env.testing` always forces safe drivers.
5. **Install dependencies** — `composer install`, plus a JS install if a frontend
   is detected.
6. **Generate a fresh `APP_KEY`.**
7. **Provision storage** and run `storage:link`.
8. **Create the isolated database** (unless `--shared-db`), recording it in the
   registry **at creation time**.
9. **Migrate** (and seed if configured) — skipped entirely under `--shared-db`.
10. **Run `post_up_hooks`.**
11. **envaudit gate** — run the project's installed envaudit; skipped with an
    actionable message if it isn't installed, or entirely with `--no-envaudit`.
12. **Verify** — run the test command; `up` succeeds only if the suite is green.
13. **Finalize the registry and report** — slug, branch, path, DB name(s), ports,
    URL, and health.

:::note[Verification fallback]
If `test_command` is still the default and parallel testing isn't available
(no `brianium/paratest`), deskhand falls back to `php artisan test` and reports
the downgrade. An explicitly configured `test_command` is always run verbatim.
:::

## Idempotency

Re-running `up` on an existing deskhand-managed worktree **repairs, never
corrupts**: it re-materializes a missing `.env`, re-installs missing dependencies,
re-creates a missing database (without dropping data it already created), and
re-verifies. It never duplicates registry entries.
