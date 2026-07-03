---
title: Quickstart
description: Provision an isolated worktree, work in it, then tear it down.
---

Run deskhand **from inside your Laravel project** — it resolves the repository
from the current directory.

## 1. Provision a worktree

```bash
deskhand up feature/billing
```

`up` creates a git worktree for the branch (creating the branch if it doesn't
exist) and provisions a fully isolated, verified environment:

- a copied `.env` (and `.env.testing`) with a **fresh `APP_KEY`** and a tagged
  `APP_NAME`,
- an **isolated database** (SQLite by default),
- **deterministic, slug-derived ports** for the dev server and Vite,
- `composer install` (and a JS install if a frontend is detected),
- storage directories and `storage:link`,
- migrations, then a **verification run of your Pest suite**.

`up` reports success only if the suite is green. By default the worktree lands at
`.claude/worktrees/<slug>/`.

## 2. Work in it

Point an agent (or yourself) at the worktree directory and work normally:

```bash
cd .claude/worktrees/feature-billing
```

Run several `up`s for several branches to have multiple agents working the same
codebase in parallel — each with its own database, ports, and `.env`, with no
collisions.

## 3. See what's running

```bash
deskhand list             # tabular overview of all managed worktrees
deskhand status           # health-check them (dirs, DBs, ports, env)
```

## 4. Tear it down

```bash
deskhand down feature/billing
```

`down` removes **only** what deskhand created: it drops the recorded databases,
removes the storage symlink (as a link), removes the worktree and prunes refs,
and deletes the branch (pass `--keep-branch` to keep it).

:::note
`down` is destructive, so it asks for confirmation. With no interactive terminal
(the normal case for agents and CI) it refuses unless you pass `--force`.
:::

## Idempotent re-runs

Re-running `up` on an existing deskhand-managed worktree **repairs** it — it
re-materializes a missing `.env`, re-installs missing dependencies, re-creates a
missing database (without dropping data it already holds), and re-verifies. It
never duplicates registry entries or clobbers existing work.
