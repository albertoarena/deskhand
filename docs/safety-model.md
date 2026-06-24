# deskhand — Safety Model

deskhand creates and destroys databases, worktrees, and files. For a tool like that, **trust is the adoption barrier.** This document states the guarantees deskhand makes and the invariants that enforce them. These are not aspirations — they are hard rules, each backed by an explicit test in the suite.

## The cardinal rule: never drop what you didn't create

deskhand will **never drop a database it did not create.**

`deskhand down` drops **only** the databases recorded in the registry as deskhand-created. Concretely:

- If there is **no registry record** for the target, `down` drops **nothing** and says so.
- deskhand **never reconstructs a database name from a slug** in order to drop it. The slug determines names at *creation* time; *deletion* is driven exclusively by what the registry says was created. (A bug that derived names for deletion could, in principle, drop a developer's real database that happened to match a pattern — so this path simply does not exist.)

This is the single most important guarantee in the tool, and it has a dedicated, unmissable test: `down` against a target with no registry entry must drop zero databases.

## The registry is the source of truth

Everything deskhand creates is recorded in a per-repo, gitignored JSON registry. The registry — not inference, not pattern-matching — is what `down`, `list`, and `status` act on.

**Records are written at creation time, not at the end.** A database is registered the instant it is created. This means an interrupted `up` (Ctrl-C, crash, power loss) still leaves an accurate record, so a later `down` can clean up exactly what was made and nothing more.

## Idempotency: re-running `up` repairs, never corrupts

Running `up` again on an existing deskhand-managed worktree:

- re-materializes a missing `.env`,
- re-installs missing dependencies,
- re-creates a missing database (without dropping a database that already holds work),
- re-runs verification,

and it **never** duplicates registry entries or silently destroys existing data. Repair is safe to repeat.

## Teardown tolerates partial state

`down` is built to clean up half-finished or damaged environments. Each step — drop DBs, remove symlinks, remove the worktree, prune refs, free ports, deregister — is best-effort and independently guarded. If the worktree directory is already gone but the database remains, `down` still drops the database. A failure in one step is reported but does not abort the others.

## Environment isolation, not leakage

- Each worktree gets its **own copied `.env`** (never a symlink), so a change in one desk cannot bleed into another.
- Each worktree gets its **own fresh `APP_KEY`**, so sessions and encryption are never shared across environments.
- `.env.testing` always forces safe drivers (array cache/session, sync queue) so the test suite never depends on — or pollutes — shared services.
- When Redis is in use, each worktree gets its own key prefix (and a logical DB index where available), so cache/session/queue namespaces don't collide.

## Symlinks are removed as links

When tearing down a Laravel `storage:link` symlink, deskhand removes the **link**, never following it into and deleting the target directory's contents.

## Public-history hygiene

The repository is developed privately and made public at v1, which exposes full git history. Therefore: no real secrets, credentials, tokens, or machine-specific absolute paths are ever committed — including in test fixtures, which use placeholder values only.

## Process execution is contained

All external command execution happens inside the concrete `ProcessRunner` and `GitRunner` implementations. No other part of the codebase calls `exec`/`shell_exec`/`proc_open` directly. This keeps the surface where side effects occur small, auditable, and fakeable in tests.

## Summary of invariants

1. Never drop a database deskhand didn't create (no record → drop nothing; never derive names for deletion).
2. Register created resources at creation time.
3. `up` is idempotent: repair, never corrupt.
4. `down` is safe on partial state.
5. Copy `.env`, never symlink it; fresh `APP_KEY` per worktree.
6. Remove `storage:link` symlinks as links.
7. No secrets or machine paths in committed code or fixtures.
8. All process/git execution stays inside the designated runners.

Every one of these has explicit test coverage.
