---
title: Configuration
description: The deskhand.yaml reference — every key, its default, and its purpose.
---

deskhand is **zero-config** for a vanilla Laravel app: every key has a default, so
a missing or empty `deskhand.yaml` yields a fully-defaulted configuration.

For per-project needs, commit a `deskhand.yaml` at the repository root. It is pure
data (YAML, never executable PHP). Credentials and the app name are read from
`.env` — never duplicated into config.

## Keys and defaults

| Key | Default | Purpose |
|---|---|---|
| `db_connection` | project's default connection from `.env` | Which DB connection deskhand clones/isolates |
| `serve_port_range` | `8300-8399` | Range for deterministic serve ports |
| `vite_port_range` | `5300-5399` | Range for deterministic Vite ports |
| `frontend_install` | `auto` | Install JS dependencies: `auto` (detect `package.json`), `true`, or `false` |
| `js_package_manager` | `auto` | JS package manager: `auto` (detect from lockfile, npm fallback), `npm`, or `yarn` |
| `seed` | `false` | Whether to seed after migrate |
| `url_strategy` | `serve` | `serve` \| `herd` \| `valet` \| `custom` (see [URL resolution](#url-resolution)) |
| `url_template` | `null` | Used when `url_strategy: custom` (e.g. `https://{slug}.acme.test`); supports `{slug}`/`{port}` |
| `url_domain` | `auto` | Domain for `herd`/`valet` hosts: `auto` (detect from `.env` `APP_URL`, fallback `test`) or an explicit domain |
| `migrate_command` | `php artisan migrate` | Migration command |
| `seed_command` | `php artisan db:seed` | Seeder command (only run if `seed: true`) |
| `test_command` | `php artisan test --parallel` | Verification command |
| `post_up_hooks` | `[]` | Ordered list of commands run after migrate, before verify |
| `redis_isolation` | `auto` | `auto` (detect), `true`, `false` |

## Example

```yaml
# deskhand.yaml
serve_port_range: 8300-8399
vite_port_range: 5300-5399
seed: true
url_strategy: herd
migrate_command: php artisan migrate --force
post_up_hooks:
  - php artisan optimize:clear
```

## Command execution rule

Every command in `migrate_command`, `seed_command`, `test_command`, and
`post_up_hooks` runs **inside the worktree directory against the worktree's
isolated `.env`**. deskhand does not parse or rewrite these commands — it sets the
working directory and environment, then executes them verbatim. This keeps
deskhand stack-agnostic.

### Worktree facts for hooks

So hooks can reach per-worktree facts without deskhand rewriting the command,
these environment variables are injected into every hook/command invocation:

`DESKHAND_SLUG`, `DESKHAND_BRANCH`, `DESKHAND_PATH`, `DESKHAND_URL`,
`DESKHAND_SERVE_PORT`, `DESKHAND_VITE_PORT`, `DESKHAND_DB_ENGINE`,
`DESKHAND_DB_NAME`.

Read them like any shell variable — deskhand performs no string templating of the
commands themselves.

## URL resolution

The reported worktree URL is resolved by the first rule that matches (highest
priority first), and persisted in the registry:

1. **`--url=<value>` flag** — authoritative, used verbatim after substituting
   `{slug}`/`{port}`.
2. **`url_strategy: custom`** — render `url_template`. If `custom` is selected but
   `url_template` is null, deskhand fails with a clear message.
3. **`url_strategy: herd` / `valet`** — `http://<slug>.<domain>`, where `<domain>`
   is `url_domain` if set, else auto-detected from `.env` `APP_URL` (the base
   app's own leftmost label is removed; e.g. `app.acme.dev` → `<slug>.acme.dev`).
   Falls back to `test` if `APP_URL` is absent.
4. **`url_strategy: serve`** (default) — `http://127.0.0.1:<assigned serve port>`.

deskhand only *computes and reports* Herd/Valet URLs; it does not run
`herd link`/`valet link` (staying stack-agnostic). Park the worktrees directory in
Herd/Valet yourself for the host to resolve.
