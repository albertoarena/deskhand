# deskhand — Implementation Brief

> **Status:** Agreed specification, ready for implementation.
> **Audience:** Claude Code (or any engineer) implementing deskhand from scratch.
> **Authority:** This document is prescriptive. Implement exactly what is specified here. Where this document names a class, interface, file path, flag, config key, or behaviour, implement it as written. Internal implementation detail (private method bodies, idiomatic PHP choices) is left to the implementer **only where this document does not specify it**. Do not redesign the architecture, rename the commands/flags/config keys, or change the safety invariants. If something is genuinely ambiguous or appears contradictory, stop and ask rather than guessing.

---

## 1. What deskhand is

`deskhand` is a stack-agnostic CLI that turns a bare `git worktree` into a **fully runnable, test-passing, isolated Laravel environment**, so that multiple AI coding agents (or humans) can work on the same codebase in parallel without colliding on files, databases, or ports.

One-line positioning (use verbatim as the README opener and docs description):

> **deskhand — isolated, test-passing Laravel environments per worktree, for running parallel AI coding agents.**

The metaphor: each agent gets its own **desk** (an isolated worktree environment); deskhand is the **hand** that sets up the desk and keeps it working.

### The problem it solves

Running multiple agents against one checkout causes three failure classes: file collisions, shared-database corruption (especially fatal for event-sourcing projection rebuilds), and context confusion (agents reading each other's in-progress changes). Git worktrees isolate **code** but not the **runtime environment** — a raw `git worktree add` gives you tracked files only: no `.env`, no `vendor/`, no `node_modules/`, no `APP_KEY`, no database, no storage symlink. deskhand closes that environment gap and verifies the result by running the test suite.

### Coordinates

- Repo: `github.com/albertoarena/deskhand`
- Composer package: `albertoarena/deskhand`
- Binary name: `deskhand`
- Docs site: `albertoarena.github.io/deskhand`
- License: MIT

---

## 2. Technology & distribution

- **Language:** PHP, **floor 8.3** (`"php": "^8.3"` in `composer.json`).
- **CLI framework:** Symfony Console.
- **Distribution:** global Composer tool (`composer global require albertoarena/deskhand`) **and** a standalone PHAR built with [Box](https://github.com/box-project/box), attached to GitHub Releases.
- **Test framework:** Pest.
- **Static analysis:** PHPStan (max level the codebase can sustain; start at a high level and do not lower it to silence real issues). **Larastan is intentionally not used** — deskhand is a standalone, framework-free CLI: the generic core imports no Illuminate classes and `LaravelProfile` only shells out to `php artisan`, so there is no Laravel magic for Larastan to analyze, and the tool should not depend on the framework it operates on.
- **Code style:** Laravel Pint.

### Why a global PHP tool, not a project dependency

deskhand operates **on** Laravel projects from the outside; it is **not** part of any project's dependency tree. It carries its own dependencies (its own `vendor/` inside the PHAR / global install), which is precisely why the "project deps don't exist yet during `up`" bootstrap problem does not apply — deskhand never relies on the target project's autoloader to run.

**Consequence for PHP version:** the PHP that matters is the one on the **user's machine running deskhand** (their global PHP), not the version their project pins. deskhand must therefore:

- Declare `"php": "^8.3"`.
- On startup, **detect the running PHP version** and, if below 8.3, exit with a clear, friendly message: `deskhand requires PHP 8.3 or newer. You are running PHP X.Y.Z.` — never a stack trace.

---

## 3. Architecture

### 3.1 Layering: generic core + pluggable stack profile

deskhand has two layers. Keep them strictly separated.

1. **Generic core** — knows nothing about Laravel. Responsible for: worktree creation, slug derivation, deterministic port allocation, the registry, `.env` copying, isolated SQLite/MySQL database lifecycle, capability detection, safe teardown, process execution, git operations.
2. **Stack profile** — the Laravel-specific behaviour: `key:generate`, `storage:link`, running the (configurable) migrate/seed/test commands, `.env.testing` driver forcing, `envaudit` invocation, Pest parallel verification, frontend detection.

**v1 ships exactly one profile: `LaravelProfile`.** The seam must exist (a `StackProfile` interface), so that WordPress/Symfony/plain-PHP profiles are *possible* for future contributors, but **do not implement any profile other than Laravel.** Do not add speculative abstraction beyond the single interface and its one implementation.

### 3.2 Interfaces (the testability seams)

All side-effecting operations sit behind interfaces so the logic can be unit-tested with fakes. Implement each as a PHP `interface` with a real implementation and a test fake/double.

| Interface | Responsibility | Real impl shells out to / touches |
|---|---|---|
| `GitRunner` | git operations: `worktree add`, `worktree remove`, `worktree prune`, `worktree list`, branch existence checks | `git` |
| `ProcessRunner` | run an external command in a given working directory with a given environment, capture exit code + stdout + stderr | `proc_open` / Symfony Process |
| `DatabaseProvisioner` | create/drop a database, test connectivity; one implementation per engine (`SqliteProvisioner`, `MysqlProvisioner`) selected at runtime | filesystem (SQLite file) / MySQL client |
| `Registry` | read/write/query the persisted record of what deskhand created | gitignored JSON file on disk |
| `EnvMaterializer` | derive a worktree `.env` and `.env.testing` from a base `.env`, injecting per-worktree overrides | filesystem |
| `CapabilityDetector` | detect presence/version of: composer, npm, the test runner (Pest vs PHPUnit), MySQL client, frontend (package.json), storage dir need | filesystem / `--version` probes |
| `StackProfile` | stack-specific provisioning/verification steps (Laravel impl: `LaravelProfile`) | composes the above |

**Rule:** command classes and orchestration logic depend on these **interfaces**, never on the concrete implementations or on global functions like `exec()`/`shell_exec()` directly. The only place real process/filesystem/db calls happen is inside the concrete implementations.

### 3.3 Suggested directory layout

```
deskhand/
├── bin/
│   └── deskhand                  # console entrypoint (registers commands, runs version guard first)
├── src/
│   ├── Console/
│   │   ├── Command/
│   │   │   ├── UpCommand.php
│   │   │   ├── DownCommand.php
│   │   │   ├── ListCommand.php
│   │   │   └── StatusCommand.php
│   │   └── Application.php        # Symfony Console Application subclass; PHP version guard
│   ├── Core/
│   │   ├── Git/                   # GitRunner interface + impl
│   │   ├── Process/               # ProcessRunner interface + impl
│   │   ├── Database/              # DatabaseProvisioner interface + Sqlite/Mysql impls
│   │   ├── Registry/              # Registry interface + JsonRegistry impl + record DTOs
│   │   ├── Env/                   # EnvMaterializer interface + impl
│   │   ├── Capability/            # CapabilityDetector interface + impl
│   │   ├── Naming/                # Slug, deterministic port allocation, DB naming
│   │   └── Config/                # Config loader + Config DTO + defaults
│   ├── Profile/
│   │   ├── StackProfile.php       # interface
│   │   └── Laravel/
│   │       └── LaravelProfile.php
│   └── Exception/                 # typed exceptions (see §10)
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fakes/                     # fakes for each interface
├── skill/
│   └── SKILL.md                   # versioned Claude Code skill artifact (see §13)
├── website/                       # Astro Starlight docs site (see §14)
├── docs/                          # these design documents
│   ├── implementation.md
│   ├── architecture.md
│   ├── safety-model.md
│   └── isolation-model.md
├── .github/workflows/
│   ├── tests.yml
│   ├── release.yml
│   └── docs.yml
├── box.json                       # PHAR build config
├── composer.json
├── phpstan.neon
├── pint.json
├── CLAUDE.md
├── CONTRIBUTING.md
├── LICENSE
└── README.md
```

---

## 4. Commands

Four commands. Names, arguments, and flags are fixed — do not rename.

### 4.1 `deskhand up <branch>`

Provision a fully runnable, verified, isolated environment for `<branch>`.

**Argument:**
- `branch` (required) — the git branch. If it exists, attach to it; if not, create it.

**Flags:**
- `--path=<dir>` — worktree location. Default: `.claude/worktrees/<slug>` relative to the repo root.
- `--db=<engine>` — `sqlite` (default) or `mysql`.
- `--shared-db` — use the base project's database instead of an isolated one (intended for **read-only** agent sessions). Mutually exclusive with full isolation; when set, deskhand treats the shared DB as read-only: skip DB creation and numbered-test-DB creation, **skip migrate and seed** (never mutate the shared DB's schema or rows), and point the worktree `.env` at the shared DB. `up` reports clearly that it did so.
- `--no-envaudit` — skip the envaudit gate.
- `--no-redis-isolation` — skip Redis prefix/index injection even if Redis is detected.
- `--no-verify` — skip the Pest verification step (provision only). *(Include this; it is useful for debugging and CI, and verification is otherwise mandatory.)*
- `--url=<value>` — override the reported worktree URL for this run. Accepts a literal URL or a template with `{slug}`/`{port}` placeholders (e.g. `--url=https://billing.acme.test` or `--url=https://{slug}.acme.test`). Authoritative — takes precedence over `url_strategy`/`url_template`/`url_domain` (see §7.1). Persisted in the registry.

**Ordered steps (the happy path):**

1. **Preflight.** Confirm we are inside a git repository. Load config (§9). Run capability detection (§8); fail early with a clear message if a required capability is missing (e.g. `--db=mysql` requested but no MySQL client). **Ensure `.gitignore` entries** (see §5.2): idempotently add deskhand's managed block to the base repo's `.gitignore` so its artifacts are never accidentally committed; report any additions.
2. **Resolve slug & derived names** (§7) from `<branch>`. If the slug already has a registry record for a *different* branch, fail with a clear collision message (§7, "Slug collisions"); a record for the same branch is the idempotent re-run path.
3. **Create the worktree.** Via `GitRunner`. Attach if branch exists, else create branch. Target path per `--path` / default.
4. **Materialize env.** Via `EnvMaterializer`: copy (never symlink) base `.env` → worktree `.env` and `.env.testing`; inject per-worktree overrides (DB name/connection, `APP_NAME` tag = `<base app name> [<slug>]`, assigned ports, Redis prefix/index if applicable). `.env.testing` **always** forces safe drivers (`array` cache/session, `sync` queue) regardless of Redis detection.
5. **Dependencies.** `composer install` always. **`composer install` must run before any artisan-dependent step** (APP_KEY, storage link, migrate, verify), because `php artisan` cannot boot without the worktree's `vendor/`.

   If a frontend is detected (`package.json` present) and `frontend_install` is not `false`, install JS dependencies (do **not** symlink `node_modules`). Choose the package manager and command as follows:
   - **Detect the manager from the committed lockfile** (the source of truth): `yarn.lock` → **yarn**; `package-lock.json` → **npm**.
   - **Ambiguous cases — neither lockfile, or both present** — fall back to the `js_package_manager` config key; when that is `auto`, default to **npm** and surface the chosen manager in the report.
   - **Command** (always with `--prefer-offline` to reuse the shared cache):

     | Manager | Lockfile present | No lockfile |
     |---|---|---|
     | npm | `npm ci --prefer-offline` | `npm install --prefer-offline` |
     | yarn | `yarn install --immutable` (Berry) / `--frozen-lockfile` (Classic) | `yarn install` |

   `npm ci` / `yarn --immutable`/`--frozen-lockfile` are used **only** when the matching lockfile exists (they require it); otherwise fall back to the plain install. If no frontend is detected, skip JS install silently. v1 supports **npm and yarn only** (pnpm is out of scope; may be added later).
6. **APP_KEY.** Generate a fresh key for this worktree (`php artisan key:generate`, run by `LaravelProfile` after dependencies exist). Never reuse the base key.
7. **Storage.** Create required storage directories and run `storage:link` (Laravel profile). Symlink creation must degrade gracefully where elevation is unavailable (fall back to a directory junction equivalent only on platforms that need it — but note Windows is out of scope, so target macOS/Linux symlink semantics).
8. **Database.** Unless `--shared-db`: create the isolated **main** DB and register it in the registry **at creation time** (safety invariant §6). Numbered Pest parallel-test DBs (N = CPU core count) are handled **per engine**:
   - **SQLite (default) — delegate to Laravel.** Do **not** pre-create the test DBs. `php artisan test --parallel` creates the per-token files itself, alongside the main file under the deskhand-exclusive, gitignored `database/deskhand/` directory. They are **not** individually registered; `down` removes them by clearing the worktree's files in that deskhand-owned directory (directory-scoped removal — never name-derived server-side deletion, see §6).
   - **MySQL — deskhand owns + registers.** deskhand creates the numbered test DBs (`<base>_wt_<slug>_test_1 … _N`) and **registers each at creation time**, so `down` drops exactly those. The verification suite must run in **reuse mode** — do not let Laravel drop/recreate the registered DBs.
   - **Fallback (A):** if a future need requires uniform behaviour, deskhand may own + register the test DBs for SQLite too (creation/teardown is trivial for files); the MySQL path above is that model already.
9. **Migrate.** *Skipped entirely under `--shared-db`* — the shared DB is treated as read-only (no migrate, no seed), and `up` reports this. Otherwise: run the configured `migrate_command` (default `php artisan migrate`) against the worktree's main DB. For **MySQL**, also migrate each deskhand-created numbered test DB (by setting the active connection/database via environment before each invocation — the migrate command itself takes no DB argument and acts on the current connection). **This does not violate the verbatim rule (§9):** deskhand does not rewrite the command string — it only sets the *environment* the unchanged `migrate_command` runs in (the same mechanism as the worktree `.env` and `DESKHAND_*` injection). This per-test-DB iteration is **`LaravelProfile` orchestration**, not generic-core logic. For **SQLite**, skip migrating the test DBs (Laravel `--parallel` migrates the files it creates). Seed only if `seed: true` in config, using `seed_command`.
10. **post_up_hooks.** Run any configured hooks, in order, inside the worktree against its `.env`.
11. **envaudit gate.** Unless `--no-envaudit`: run the **project's installed** envaudit (the worktree's `vendor/bin/envaudit`, present after step 5) via `ProcessRunner`, inside the worktree against its `.env`. Running the project's own copy is deliberate — envaudit validates against the *project's* env schema/usage, which only its in-project install can see; deskhand does not bundle or rewrite it, it merely invokes it (consistent with the command-hook execution rule, §9). Behaviour:
    - If envaudit reports errors, **fail `up`** with envaudit's output surfaced. This catches a malformed/incomplete `.env` before verification wastes time.
    - If envaudit is **not installed** in the project, **do not fail** — skip the gate with a clear, actionable message (e.g. `envaudit not found in the project — skipping the env gate. Run "composer require --dev albertoarena/envaudit" to enable it, or pass --no-envaudit to silence this.`).
    - `--no-envaudit` skips the gate entirely without any message about installing it.
12. **Verify.** Unless `--no-verify`: run the configured `test_command` (default `php artisan test --parallel`) in the worktree. Success is reported **only if the suite is green.** A failing suite fails `up`.
    - **`--parallel` fallback (default command only):** if `test_command` is still the **default** and capability detection finds parallel testing unavailable (e.g. `brianium/paratest` not installed), fall back to `php artisan test` (no `--parallel`) and report the downgrade. If the user has **explicitly configured** `test_command`, run it **verbatim** (§9 rule) — never rewrite it; if it then fails on a missing capability, surface the error clearly rather than mutating the command.
13. **Finalize registry & report.** Persist the full worktree record (§5.1). Print a concise summary: slug, branch, path, DB name(s), ports, URL (per url strategy), and health.

**Idempotency:** Re-running `up` on an existing deskhand-managed worktree must **repair, not corrupt** — re-materialize missing env, re-install missing deps, re-create missing DBs (without dropping existing data it created), and re-verify. It must never duplicate registry entries or drop-and-recreate a DB that already holds work unless explicitly asked.

### 4.2 `deskhand down <branch|slug>`

Tear down a worktree environment, removing **only** what deskhand created.

**Argument:**
- `branch|slug` (required) — identifies the worktree via the registry.

**Flags:**
- `--keep-branch` — remove the worktree but leave the git branch in place.
- `--force` — proceed without interactive confirmation.

**Confirmation behaviour:** `down` is destructive, so by default it asks for interactive confirmation before tearing anything down. When input is **non-interactive** (no TTY — the normal case for AI agents and CI), deskhand must **not** silently proceed: if `--force` was not passed, fail fast with a clear, actionable message (e.g. `down needs confirmation but no interactive terminal is available. Re-run with --force to proceed.`). Detect interactivity via Symfony Console's `$input->isInteractive()`. Unattended callers always pass `--force`.

**Ordered steps:**

1. Look up the registry record. If none exists, refuse to act destructively and say so (do not infer/guess DB names from the slug for dropping — see §6).
2. Drop **only** the DBs listed in that registry record as deskhand-created. Never drop anything untagged.
3. Remove storage symlinks **as links** (never follow them into their target directories).
4. `git worktree remove` (then `git worktree prune` to clear orphaned refs). Remove the branch unless `--keep-branch`.
5. Free/release allocated ports (no-op for deterministic ports beyond registry cleanup, but release any lockfiles if used).
6. Remove the registry entry.

**Safety on partial state:** `down` must succeed even on a half-provisioned or interrupted worktree (e.g. DB created but worktree dir already gone). Each teardown step is best-effort and independently guarded; a failure in one step is reported but does not abort cleanup of the others.

### 4.3 `deskhand list`

List all deskhand-managed worktrees from the registry: slug, branch, path, DB engine + name, ports, URL, and creation time. Tabular output. `--json` for machine-readable output.

### 4.4 `deskhand status [<branch|slug>]`

Health check. With no argument: summarize all managed worktrees and flag problems (worktree dir missing, DB unreachable, port in use by something else, env missing). With an argument: detailed health of one worktree. `--json` supported.

---

## 5. The registry

The registry is the **single source of truth** for what deskhand created, and therefore for what it is allowed to destroy.

- **Storage:** a single JSON file at the fixed per-repo path **`.claude/deskhand/registry.json`** (relative to the base repo root), gitignored via the §5.2 managed block. **Intentionally never committed** — it is per-clone, per-machine local state (which DBs/worktrees/ports exist *on this machine*), like `.env`/`vendor/`. Committing it would push one machine's resource map to others, where `down` could act on records that don't match their local reality. Only `.claude/deskhand/` and `.claude/worktrees/` are ignored — the rest of `.claude/` stays trackable.
- **Access:** only through the `Registry` interface.
- **Write timing:** created resources (especially databases) are recorded **at the moment of creation**, not at the end of `up` — so an interrupted `up` still leaves an accurate record for `down` to clean up.

### 5.1 Record shape (per worktree)

```json
{
  "slug": "feature-billing",
  "branch": "feature/billing",
  "path": ".claude/worktrees/feature-billing",
  "created_at": "2026-06-24T10:00:00Z",
  "db": {
    "engine": "sqlite",
    "shared": false,
    "main": "database/deskhand/feature-billing.sqlite",
    "test_dbs": []
  },
  "ports": { "serve": 8312, "vite": 5312 },
  "redis": { "isolated": false, "prefix": null, "db_index": null },
  "url": "http://127.0.0.1:8312"
}
```

`test_dbs` lists only the numbered parallel-test databases **deskhand created and is responsible for dropping**:

- **SQLite (default):** the per-token test files are created by Laravel's `--parallel` runner, not deskhand, so `test_dbs` is empty (as above). Those files are removed by `down` clearing the worktree's files under the deskhand-owned `database/deskhand/` directory.
- **MySQL:** deskhand creates and registers each test DB, so `test_dbs` holds their **database names** (e.g. `acme_wt_feature-billing_test_1`); `down` drops exactly those. For MySQL, `main` also holds a database name rather than a file path, and `engine` is `mysql`.

### 5.2 `.gitignore` management

deskhand creates artifacts that must never be committed (the registry, the SQLite directory, the worktrees). On `up`, deskhand **idempotently** ensures a managed block in the **base repo's** `.gitignore`:

```
# deskhand (managed)
.claude/worktrees/
.claude/deskhand/
database/deskhand/
```

Rules:
- Scope to these **specific subpaths** — never ignore all of `.claude/` (other tooling may track files there).
- Idempotent: check for each line before appending; never duplicate. Add only missing lines (the block is recognizable by its `# deskhand (managed)` marker).
- Report any additions in the `up` summary. Never reorder or remove unrelated entries.

---

## 6. Safety invariants (HARD RULES — never violate)

These are non-negotiable. Treat any change that weakens them as a bug.

1. **Never drop a database deskhand did not create.** `down` drops **only** DBs recorded in the registry as deskhand-created. If there is no registry record, deskhand must not drop anything. Never reconstruct DB names from a slug for the purpose of dropping.
2. **Record before create-effect persists.** Persist the registry entry for a database at creation time, so an interrupted run is still cleanable.
3. **Idempotent `up`.** Re-running `up` repairs; it never corrupts existing work or duplicates registry entries.
4. **Teardown is safe on partial state.** `down` tolerates missing pieces and never aborts the whole cleanup because one step failed.
5. **Copy `.env`, never symlink it.** A worktree must have its own `.env` so changes don't bleed across desks; each desk has its own `APP_KEY`.
6. **Remove symlinks as links.** When tearing down `storage:link` symlinks, never follow them into their targets.
7. **Public-history hygiene.** No real secrets, credentials, or machine-specific absolute paths in committed code or test fixtures. The repo will be made public and full history will be exposed. Test fixtures use placeholder values only.
8. **No `exec`/`shell_exec` outside the concrete `ProcessRunner`/`GitRunner` implementations.** Everything else depends on the interfaces.

---

## 7. Naming, slugs & deterministic ports

- **Slug:** derive a filesystem- and DB-safe slug from the branch name (e.g. `feature/billing` → `feature-billing`). Lowercase; replace `/` and non-alphanumerics with `-`; collapse repeats; trim. The slug is the join key across worktree path, DB name(s), `APP_NAME` tag, and registry record.
- **Slug collisions:** different branches can derive the same slug (e.g. `feature/billing` and `feature-billing`). deskhand **never auto-disambiguates** with a suffix — that would break the "same branch → same slug/ports/DBs" determinism. Instead, on `up`, if the derived slug already has a registry record for a **different** branch, fail with a clear message (e.g. `slug 'feature-billing' is already used by branch 'feature/billing'. Choose a different branch name or pass --path.`). A record for the **same** branch is the normal idempotent re-run, not a collision.
- **APP_NAME tag:** `<base> [<slug>]`, so environments are distinguishable in logs, mail, and browser. `<base>` is the base `.env`'s `APP_NAME`; if that is **absent or empty**, fall back to the project root directory name (e.g. `acme [feature-billing]`). Never emit a tag with a leading space or empty name.
- **Deterministic ports:** derive a stable port per service from a hash of the slug, mapped into a configured range (default ranges in config). Same branch → same ports every time (not a free-port scan). If a derived port is genuinely occupied by a foreign process, surface it in `status`/`up` rather than silently reassigning; offer a clear message.
- **Redis namespacing (when isolation is active):** the per-slug key **prefix** is the primary, effectively-unlimited isolation mechanism and alone guarantees separation. A logical **DB index** is a best-effort bonus, derived deterministically as `hash(slug) % 16` (same branch → same index, mirroring the ports philosophy). Index collisions are **tolerated, never a failure** — once worktree count approaches/exceeds 16 the prefix still isolates correctly; `status` may note when two worktrees share an index. deskhand does **not** scan for a free index (that would break determinism). Both prefix and index are recorded in the registry.
- **DB names/paths (canonical scheme):**

  | | SQLite (default) | MySQL |
  |---|---|---|
  | **Main** | `database/deskhand/<slug>.sqlite` | `<base>_wt_<slug>` |
  | **Test #n** | `database/deskhand/<slug>_test_<n>.sqlite` | `<base>_wt_<slug>_test_<n>` |

  where `<slug>` is the branch-derived slug (above), `<base>` is the project's base database name from `.env` `DB_DATABASE`, and `<n>` runs `1 … N` (N = CPU core count).

  - **SQLite** files live in the deskhand-exclusive, gitignored `database/deskhand/` directory — the directory namespaces them, so no `_wt_` infix is needed. **MySQL** databases share the server namespace, so the `_wt_` ("worktree") infix is **kept**, disambiguating deskhand's DBs from the project's real `<base>` database and from other tooling. Do not drop the `_wt_` infix.
  - The **SQLite test-row is illustrative only.** Per §4.1 step 8, SQLite parallel-test DBs are created by Laravel's `--parallel` runner (not deskhand), so their exact on-disk names follow Laravel's token convention; deskhand neither enforces nor registers them (teardown is directory-scoped, §4.2). The **MySQL test names are authoritative** — deskhand creates, registers, and drops exactly those.

### 7.1 URL resolution

The reported worktree URL is resolved by the first rule that matches (highest priority first), and the result is persisted in the registry and shown by `list`/`status`:

1. **`--url=<value>` flag** — authoritative. Used verbatim, after substituting `{slug}`/`{port}` if present.
2. **`url_strategy: custom`** — render `url_template` (substituting `{slug}`/`{port}`). If `custom` is selected but `url_template` is null, fail with a clear message.
3. **`url_strategy: herd` or `valet`** — `http://<slug>.<domain>`, where `<domain>` is:
   - the `url_domain` config value if set to an explicit domain, else
   - **auto-detected** from the base `.env` `APP_URL`: take its host and **remove the leftmost label** (the base app's own subdomain); the remainder is the domain. Examples: `myapp.test` → `test` (URL `http://<slug>.test`); `app.acme.dev` → `acme.dev` (URL `http://<slug>.acme.dev`); single-label hosts like `localhost` are used as-is (`http://<slug>.localhost`).
   - if `APP_URL` is absent/unparseable, fall back to `test`.
4. **`url_strategy: serve`** (default) — `http://127.0.0.1:<assigned serve port>`.

deskhand only *computes and reports* `herd`/`valet` URLs; it does not run `herd link`/`valet link` (staying stack-agnostic). The user must have the worktrees directory parked in Herd/Valet for the host to resolve; `status` may note when it does not.

---

## 8. Capability detection

Detect, do not assume. Before/within `up`, probe and fail with a clear, actionable message when a required capability is missing:

- **composer** — required, always.
- **JS package manager (npm or yarn)** — required only if a frontend is detected (`package.json` present) and `frontend_install` is not `false`. Detect the manager from the lockfile (`yarn.lock` → yarn, `package-lock.json` → npm), falling back to `js_package_manager` config (default npm) when ambiguous. If a frontend exists but the resolved manager's binary is **not installed**, fail clearly. If no frontend, skip silently. (v1: npm and yarn only.)
- **test runner** — detect that a usable test runner is present (the default `test_command` `php artisan test` works for both Pest and PHPUnit; deskhand runs it verbatim, so the Pest-vs-PHPUnit *identity* is not needed). Also detect **whether parallel testing is available** (`brianium/paratest`), which drives the `--parallel` fallback in §4.1 step 12. If config overrides `test_command`, respect the override and run it verbatim.
- **MySQL client** — required only if `--db=mysql`; fail clearly if missing.
- **storage link need** — detect whether `storage:link` applies.
- **PHP version** — the §2 startup guard (≥ 8.3).

Never half-provision: if a required capability is missing, fail before creating side effects where possible, and ensure anything already created is registry-recorded so `down` can clean it.

---

## 9. Configuration

A committed, per-project **YAML** config file: **`deskhand.yaml`** at the repo root. Zero-config must still work for a vanilla Laravel app (every key has a default). Credentials and app name are read from `.env`, never duplicated into config.

### Keys & defaults

| Key | Default | Purpose |
|---|---|---|
| `db_connection` | (the project's default connection from `.env`) | Which DB connection deskhand clones/isolates |
| `serve_port_range` | `8300-8399` | Range for deterministic serve ports |
| `vite_port_range` | `5300-5399` | Range for deterministic Vite ports |
| `frontend_install` | `auto` | Whether to install JS dependencies: `auto` (detect frontend via `package.json`), `true`, or `false` |
| `js_package_manager` | `auto` | Which JS package manager: `auto` (detect from lockfile, npm fallback), `npm`, or `yarn` |
| `seed` | `false` | Whether to seed after migrate |
| `url_strategy` | `serve` | `serve` \| `herd` \| `valet` \| `custom` (see URL resolution, §7.1) |
| `url_template` | `null` | Used when `url_strategy: custom` (e.g. `https://{slug}.acme.test`); supports `{slug}`/`{port}` |
| `url_domain` | `auto` | Domain for `herd`/`valet` hosts (`<slug>.<url_domain>`): `auto` (detect from `.env` `APP_URL`, fallback `test`) or an explicit domain (e.g. `acme.test`) |
| `migrate_command` | `php artisan migrate` | Migration command (override example: `php artisan migrations`) |
| `seed_command` | `php artisan db:seed` | Seeder command (only run if `seed: true`) |
| `test_command` | `php artisan test --parallel` | Verification command |
| `post_up_hooks` | `[]` | Ordered list of commands run after migrate, before verify |
| `redis_isolation` | `auto` | `auto` (detect), `true`, `false` |

**Command-hook execution rule:** every command in `migrate_command` / `seed_command` / `test_command` / `post_up_hooks` runs **inside the worktree directory against the worktree's isolated `.env`**. deskhand does not parse or rewrite these commands; it sets the working directory and environment, then executes them verbatim. This keeps deskhand stack-agnostic — it does not need to understand what a custom command does.

**Worktree facts for hooks (`DESKHAND_*` env vars):** so hooks can reach per-worktree facts **without** deskhand rewriting the command (preserving the verbatim rule), deskhand injects these environment variables into every hook/command invocation, alongside the worktree `.env`: `DESKHAND_SLUG`, `DESKHAND_BRANCH`, `DESKHAND_PATH`, `DESKHAND_URL`, `DESKHAND_SERVE_PORT`, `DESKHAND_VITE_PORT`, `DESKHAND_DB_ENGINE`, `DESKHAND_DB_NAME` (the main DB name/path). Most env facts (DB name, ports, `APP_NAME`) are also already present via the worktree `.env`; the `DESKHAND_*` set guarantees them under stable names — the **slug** in particular is otherwise unavailable. Hooks read them like any shell variable; deskhand performs **no string templating** of the commands themselves.

**Format:** YAML, parsed with `symfony/yaml` (deskhand bundles its own dependencies, so this adds nothing to the target project). The file is pure data — never executable PHP. The loader applies the defaults above for any key the user omits, so a missing or empty `deskhand.yaml` yields a fully-defaulted config.

---

## 10. Error handling & exceptions

- Define typed exceptions under `src/Exception/` (e.g. `NotAGitRepositoryException`, `MissingCapabilityException`, `WorktreeExistsException`, `DatabaseProvisionException`, `VerificationFailedException`, `RegistryException`).
- Every user-facing failure prints a clear, actionable message (what failed, why, what to do) — never a raw stack trace in normal operation. A `--verbose`/`-v` flag may surface stack traces for debugging (Symfony Console provides this).
- Exit codes: `0` success; non-zero on failure, with distinct codes for distinct failure classes so scripts/CI/agents can branch on *why* a run failed. Define them as constants tied to each exception type:

  | Code | Meaning | Typed exception |
  |---|---|---|
  | `0` | Success | — |
  | `1` | Generic / unexpected error | (uncaught / base) |
  | `2` | Not a git repository | `NotAGitRepositoryException` |
  | `3` | Missing capability | `MissingCapabilityException` |
  | `4` | Worktree already exists / conflict (incl. slug collision) | `WorktreeExistsException` |
  | `5` | Database provisioning failed | `DatabaseProvisionException` |
  | `6` | Verification (test suite) failed | `VerificationFailedException` |
  | `7` | Registry error | `RegistryException` |

  Code `6` (verification failed) is deliberately distinct so CI can tell "deskhand couldn't provision" from "provisioning worked but the suite is red." Code `1` is the catch-all for anything without a specific class.

---

## 11. Testing strategy (TDD — mandatory)

**Write tests first.** Every command and subsystem is specified above with explicit behaviour; turn that behaviour into Pest expectations before implementing.

- **Unit tests** (the bulk): test orchestration logic with **fakes** for every interface (`GitRunner`, `ProcessRunner`, `DatabaseProvisioner`, `Registry`, `EnvMaterializer`, `CapabilityDetector`). No real git, no real DB, no real processes. Cover: slug derivation, deterministic port allocation, env materialization + override injection, registry read/write/query, the safety invariants (especially "never drop untagged DB" — assert that `down` with no registry record drops nothing), idempotent `up`, capability-missing failures, config loading + defaults + `DESKHAND_*` hook env injection (no command-string templating).
- **Integration tests** (thin): exercise the real implementations against a **temporary git repo + SQLite** in a temp directory. Cover the real `up`→`verify`→`down` round trip. Keep these few and fast; they prove the wiring, not the logic. Two tiers:
  - **Default — stub Laravel-shaped fixture (fast, hermetic, every CI cell).** A committed minimal fixture (e.g. `tests/Fixtures/laravel-app/`) that is *shaped* like a Laravel project without pulling the framework: a minimal `composer.json` (installs quickly, no heavy deps), a **stub `artisan`** executable that responds to the exact commands deskhand invokes (`key:generate`, `storage:link`, `migrate`, `test`) with realistic exit codes/output, an `.env.example` with **placeholder values only** (safety invariant #7), a Pest/PHPUnit config with **≥1 passing test**, and a migration so `migrate` does real work against SQLite. This proves deskhand's orchestration and the real git/process/db/fs wiring without depending on Laravel itself.
  - **Optional — real minimal Laravel app (high-fidelity, gated).** One round-trip test that scaffolds a genuine minimal Laravel app (real `php artisan`), marked slow and **not required on every matrix cell** — gated behind a `@group real-laravel` / env flag so it can run in a dedicated/nightly job. Gives one true-fidelity proof that `up` produces a worktree whose real `php artisan test` passes.
- **Fakes** live in `tests/Fakes/` and are reusable across tests.
- **Coverage expectation:** all safety invariants (§6) must have explicit, named tests. The "never drop untagged DB" rule in particular must be unmissable in the suite.
- **Idempotency (invariant #3) — named scenarios.** `up` repair behaviour must be tested explicitly for each kind of partial/existing state:
  - re-run after **interrupted post-DB-creation, pre-migrate** → completes migrate, does **not** recreate or drop the existing DB, adds **no** duplicate registry entry;
  - re-run with a **deleted `.env`** → re-materializes it (with the worktree's own `APP_KEY`);
  - re-run with a **missing `vendor/`** → re-installs dependencies;
  - re-run on a **fully provisioned** worktree → no-op repair, suite still green, registry unchanged.
  Key assertions across all: **no duplicate registry entries** and **no drop-and-recreate of a DB that already holds data**.
- Run under the PHP 8.3/8.4/8.5 matrix in CI.

---

## 12. CI/CD — three GitHub Actions workflows

### 12.1 `tests.yml` — on push & pull_request
- Matrix: PHP `8.3`, `8.4`, `8.5`. The floor stays `^8.3`. Mark the **`8.5` cell as allow-failure (non-blocking) initially** to absorb any ecosystem/tooling lag on the newest runtime; promote it to required once it is stable. `8.3` and `8.4` are required.
- Steps: checkout → setup-php → `composer install` → `composer validate --strict` → Pint check (`pint --test`) → PHPStan → Pest suite.
- All required cells must pass; this is the correctness gate.

### 12.2 `release.yml` — on tag (`v*`)
- Build the PHAR with Box.
- Attach the PHAR to the GitHub Release.
- (Optional, document only) verify the PHAR runs `deskhand --version` before attaching.

### 12.3 `docs.yml` — on push to `main` affecting `website/**`
- Build the Astro Starlight site.
- Deploy to GitHub Pages (`albertoarena.github.io/deskhand`).
- Requires the repo to be public (free-tier Pages); this is expected at v1.

---

## 13. The Claude Code skill (versioned artifact)

- Authored as **source in the repo** at `skill/SKILL.md` (plus any helper scripts). **Not** generated by CI.
- Purpose: let an agent session self-provision a deskhand worktree on demand, and know the command surface, flags, and safety rules.
- Contents: what deskhand is, when to use it, the command/flag reference, the safety invariants the agent must respect (never bypass `down`'s registry check; never run destructive DB operations directly), and a short worked example.
- CI may *validate* the skill (lint, check referenced commands/flags exist) but must not generate it.

---

## 14. Docs site (Astro Starlight)

Mirror the established envaudit docs pattern: a Starlight site under `website/`, deployed to GitHub Pages, with the blog/site as SEO source of truth.

**Information architecture:**

- **Landing (`index.mdx`):** What is deskhand / Why parallel agents need it / feature cards (full isolation, deterministic ports, safe teardown, Pest verify, stack profiles) / a terminal quick example.
- **Getting Started:**
  - Installation (global Composer & PHAR)
  - Quickstart (`up` → work → `down`)
  - Configuration (`deskhand.yaml` reference — every key + default from §9)
- **Commands:** one page each for `up`, `down`, `list`, `status` — every flag documented.
- **Concepts** (these are trust-builders; for a tool that creates and destroys databases, documenting the guarantees is itself an adoption lever):
  - **How isolation works** (`isolation-model.md` content, adapted)
  - **Safety model** (`safety-model.md` content, adapted — the never-drop-untagged-DBs invariant front and centre)

The two Concepts pages draw directly from `docs/isolation-model.md` and `docs/safety-model.md`.

---

## 15. Repo deliverables

- **`LICENSE`** — MIT, `Copyright (c) 2026 Alberto Arena`.
- **`CONTRIBUTING.md`** — dev setup, the TDD requirement, how to run Pest / PHPStan / Pint, PR conventions, and the safety invariants contributors must preserve.
- **`README.md`** — opens with the verbatim positioning line (§1); install, quickstart, command table, link to docs. Progressively updated as features land.
- **`CLAUDE.md`** — guidance for Claude Code working *in this repo* (conventions, TDD-first, the safety invariants, where things live, how to run checks).
- **`composer.json`**, **`box.json`**, **`phpstan.neon`**, **`pint.json`** — configured per the above.

---

## 16. Build sequence (implement in this order)

1. **Project skeleton:** `composer.json` (`^8.3`), Symfony Console wiring, `bin/deskhand`, PHP version guard, Pint/PHPStan/Pest configured, CI `tests.yml` green on an empty suite.
2. **Core interfaces + fakes:** define all interfaces (§3.2) and their test fakes first.
3. **Core subsystems, test-first:** Naming (slug/ports/db-names), Config (loader + defaults + `DESKHAND_*` hook env), Registry (JSON), EnvMaterializer, CapabilityDetector, DatabaseProvisioner (SQLite first, then MySQL).
   - **Split into reviewable sub-commits**, each with its own review gate and all checks green before the next: (3a) Naming → (3b) Config → (3c) Registry (JSON) → (3d) EnvMaterializer → (3e) CapabilityDetector → (3f) DatabaseProvisioner (SQLite, then MySQL). This keeps each unit small enough to review and bisect, rather than landing the whole phase in one commit.
4. **Concrete Git/Process implementations.**
5. **LaravelProfile.**
6. **Commands, test-first:** `up`, then `down` (with the safety-invariant tests), then `list`, `status`.
7. **Integration round-trip test** on a minimal fixture.
8. **CI:** finish `tests.yml` matrix; add `release.yml` (Box PHAR) and `docs.yml`.
9. **Repo deliverables:** LICENSE, CONTRIBUTING.md, README.md, CLAUDE.md.
10. **Skill artifact:** `skill/SKILL.md`.
11. **Docs site:** Astro Starlight under `website/`.
12. **Logo (last):** SVG, echoing the desk/hand motif, sibling to envaudit branding.

---

## 17. Out of scope (do not build)

- Any stack profile other than Laravel.
- Windows support (target macOS + Linux only).
- Redis isolation by default (conditional only — §4.1 step 4, §9 `redis_isolation`).
- MariaDB (MySQL only).
- Cloud/preview environments.
- The orchestration/merge layer (AGENTS.md ownership maps, PR automation). deskhand provides the isolated substrate only.
