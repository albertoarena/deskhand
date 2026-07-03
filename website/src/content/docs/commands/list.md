---
title: deskhand list
description: List all deskhand-managed worktrees from the registry.
---

```bash
deskhand list [--json]
```

List all deskhand-managed worktrees from the registry, in a table.

## Flags

| Flag | Description |
|---|---|
| `--json` | Emit machine-readable JSON instead of a table. |

## Output

Each row shows the worktree's slug, branch, path, DB engine + name, ports, URL,
and creation time — everything deskhand recorded when it provisioned the desk.

```bash
deskhand list
deskhand list --json | jq '.[].slug'
```

The registry it reads from is the per-repo, gitignored source of truth for what
deskhand created (`.claude/deskhand/registry.json`). See the
[Safety model](/deskhand/concepts/safety/).
