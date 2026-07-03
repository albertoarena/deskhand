---
title: deskhand status
description: Health-check deskhand-managed worktrees.
---

```bash
deskhand status [<branch|slug>] [--json]
```

Health-check managed worktrees and flag problems.

## Argument

| Argument | Description |
|---|---|
| `branch\|slug` | *Optional.* With no argument, summarize all managed worktrees. With one, show detailed health for that worktree. |

## Flags

| Flag | Description |
|---|---|
| `--json` | Emit machine-readable JSON. |

## What it checks

- the worktree directory exists,
- the database is reachable,
- the assigned ports are free (or flags a port held by a foreign process),
- the `.env` is present.

It may also note when two worktrees share a Redis DB index (tolerated — the
per-slug prefix still isolates them), or when a Herd/Valet host does not resolve.

```bash
deskhand status                     # all worktrees
deskhand status feature/billing     # one, in detail
deskhand status --json
```
