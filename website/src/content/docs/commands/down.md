---
title: deskhand down
description: Tear down a worktree environment, removing only what deskhand created.
---

```bash
deskhand down <branch|slug> [options]
```

Tear down a worktree environment, removing **only** what deskhand created.

## Argument

| Argument | Description |
|---|---|
| `branch\|slug` | **Required.** Identifies the worktree via the registry. |

## Flags

| Flag | Description |
|---|---|
| `--keep-branch` | Remove the worktree but leave the git branch in place. |
| `--force` | Proceed without interactive confirmation. |

## Confirmation

`down` is destructive, so by default it asks for interactive confirmation. When
input is **non-interactive** (no TTY — the normal case for AI agents and CI), it
does **not** silently proceed: without `--force` it fails fast with an actionable
message. Unattended callers should always pass `--force`.

## What it does

1. **Look up the registry record.** If none exists, `down` refuses to act
   destructively and says so — it never infers database names from the slug.
2. **Drop only the databases** that record lists as deskhand-created. A shared
   (base project) database is never dropped.
3. **Remove the storage symlink as a link** — never following it into its target.
4. **Remove the worktree** and prune orphaned refs; remove the branch unless
   `--keep-branch`.
5. **Release allocated ports.**
6. **Remove the registry entry** (last, so an interrupted teardown is retryable).

:::tip[Safe on partial state]
`down` succeeds even on a half-provisioned worktree (e.g. the database exists but
the worktree directory is already gone). Each step is best-effort and
independently guarded — a failure in one is reported but doesn't abort the others.
:::

See the [Safety model](/deskhand/concepts/safety/) for the guarantees behind
teardown.
