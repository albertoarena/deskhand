# deskhand — Architecture

This document explains the *shape* of deskhand and the reasoning behind it. For the prescriptive build spec, see [`implementation.md`](./implementation.md). For the guarantees, see [`safety-model.md`](./safety-model.md) and [`isolation-model.md`](./isolation-model.md).

## The core idea

A git worktree isolates **code** — each worktree is a separate checkout of the same repository, sharing one `.git` object store. What it does **not** isolate is the **runtime environment**: the `.env`, the `APP_KEY`, installed dependencies, the database, storage symlinks, and the ports a dev server binds to. A raw `git worktree add` hands you tracked files and nothing else.

deskhand exists to close that gap and then *prove* it closed it by running the test suite. The output of `deskhand up` is not "a worktree" — it is "a worktree whose `php artisan test` passes."

## Two layers

deskhand is deliberately split so that the parts that know about Laravel are quarantined from the parts that don't.

### Generic core

The core treats a project as a directory with a git repo, an `.env`, optional dependencies, and a database. It handles:

- worktree creation and removal (via git)
- slug derivation and deterministic, slug-stable port allocation
- the registry (what we created — the basis for safe teardown)
- copying `.env` and injecting per-worktree overrides
- isolated database lifecycle (SQLite or MySQL)
- capability detection
- safe, partial-state-tolerant teardown

None of this references Laravel.

### Stack profile

Everything Laravel-specific lives behind the `StackProfile` interface, implemented once as `LaravelProfile`: `key:generate`, `storage:link`, running the configurable migrate/seed/test commands, forcing safe drivers in `.env.testing`, the envaudit gate, Pest parallel verification, and frontend detection.

**v1 ships only `LaravelProfile`.** The interface exists so the architecture is honest about the seam — but no other profile is built. This keeps the open-source story accurate ("extensible, first-class Laravel support") without shipping speculative, untested abstraction.

## Why everything sits behind interfaces

deskhand is almost entirely **side effects**: it runs git, spawns processes, creates and drops databases, writes files. Side effects are hard to unit-test directly. So every side-effecting capability is expressed as an interface (`GitRunner`, `ProcessRunner`, `DatabaseProvisioner`, `Registry`, `EnvMaterializer`, `CapabilityDetector`, `StackProfile`), with:

- a **real implementation** that does the actual work, and
- a **fake** used in tests.

Orchestration logic (the command classes, the provisioning flow) depends only on the interfaces. This is what makes TDD viable on a tool like this: the logic — slug rules, port allocation, override injection, the registry-driven safety checks, idempotency — is tested with fakes, fast and deterministic, while a thin layer of integration tests exercises the real implementations against a temporary git repo and SQLite.

## Why a global PHP tool

deskhand operates *on* projects, from the outside. It is not a dependency of any project. It carries its own `vendor/` (or is shipped as a self-contained PHAR), so it never relies on the target project's autoloader.

This single decision resolves what looked like a bootstrap paradox: "`up` has to run before the project's dependencies exist." It does — but deskhand isn't using the project's dependencies; it's using its own. The target project's empty `vendor/` is something deskhand *fills*, not something it needs in order to start.

It also explains the PHP version policy: the PHP that matters is the **user's global PHP** running deskhand, not the version their project pins. Hence a conservative-but-modern floor (8.3) and a friendly startup version guard rather than a crash.

## Distribution

Two paths, same artifact:

- `composer global require albertoarena/deskhand` for Composer users.
- A standalone **PHAR** (built with Box, attached to each GitHub Release) for those who'd rather drop in a binary.

Docs ship as an Astro Starlight site deployed to GitHub Pages, mirroring the envaudit setup.

## What deskhand is not

It is not an orchestrator. It does not split work across agents, assign tasks, or merge branches. It provides the **isolated substrate** on which parallel agents (driven by you, by Claude Code sub-agents, or by any other harness) can work without colliding. The coordination layer — who owns which files, who merges when — is a separate concern, handled by an `AGENTS.md`/`CLAUDE.md` ownership map and a PR-per-worktree workflow, not by deskhand.
