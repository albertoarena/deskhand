# Contributing to deskhand

Thanks for your interest in contributing. deskhand is a CLI that provisions isolated, test-passing Laravel environments per git worktree, for running parallel AI coding agents. This guide covers how to set up, the standards we hold, and how to get a change merged.

## Development setup

Requirements: **PHP 8.3+** and Composer. (deskhand is a global CLI tool — the PHP that matters is the one on your machine, not any project's pinned version.)

```bash
git clone https://github.com/albertoarena/deskhand.git
cd deskhand
composer install
```

Run it locally:

```bash
php bin/deskhand --version
php bin/deskhand list
```

### Dependency resolution is pinned to PHP 8.3

`composer.json` sets `config.platform.php` to `8.3.0`, so dependency
resolution always targets the support floor regardless of which PHP you
run locally. Run plain `composer update` even if your machine is on 8.4
or 8.5 — the lock will stay installable on every supported version. Do
not remove the platform pin or hand-edit the lock; doing so can select
packages that need a newer PHP and break the 8.3 CI job.

## Running the checks

All of these must pass before a PR is merged; CI runs them across PHP 8.3, 8.4, and 8.5.

```bash
vendor/bin/pest             # test suite
vendor/bin/phpstan analyse  # static analysis (PHPStan)
vendor/bin/pint --test      # code style check
composer validate --strict
```

Fix style automatically with `vendor/bin/pint`.

## Standards

### TDD is required

Write tests first. deskhand is almost entirely side effects, so the codebase is built around interfaces with fakes — this is what makes test-first practical. Unit-test orchestration logic with the fakes in `tests/Fakes/`; keep real-world integration tests thin (a temp git repo + SQLite). New behaviour lands with the tests that specify it.

### Respect the architecture

- All side effects go through the interfaces (`GitRunner`, `ProcessRunner`, `DatabaseProvisioner`, `Registry`, `EnvMaterializer`, `CapabilityDetector`, `StackProfile`). Do not call `exec`/`shell_exec`/`proc_open` outside the designated runner implementations.
- The generic core must stay free of Laravel-specific logic. Laravel behaviour belongs in `LaravelProfile`.

### Preserve the safety invariants

These are non-negotiable and each is covered by a test (see [`docs/safety-model.md`](./docs/safety-model.md)). A change that weakens any of them will not be merged:

1. **Never drop a database deskhand did not create.** `down` acts only on the registry. No record → drop nothing. Never derive DB names from a slug to delete them.
2. Register created resources at creation time (interrupted runs stay cleanable).
3. `up` is idempotent — it repairs, never corrupts.
4. `down` is safe on partial/interrupted state.
5. Copy `.env`, never symlink it; fresh `APP_KEY` per worktree.
6. Remove `storage:link` symlinks as links, never following into targets.
7. No secrets or machine-specific paths in code or fixtures (history is public).

### Public-history hygiene

Never commit real secrets, credentials, tokens, or absolute machine paths — including in fixtures. Use placeholders.

## Pull requests

- Branch from `main`, keep PRs focused.
- Include tests for new behaviour and for any bug you fix.
- Make sure the full check suite is green locally.
- Describe what changed and why; if it touches a safety invariant or the public command/flag/config surface, call that out explicitly.

## Scope

deskhand intentionally does **not** cover: non-Laravel stack profiles, Windows, Redis isolation by default, MariaDB, cloud/preview environments, or the orchestration/merge layer. Proposals that expand scope are welcome as discussion issues first.

## License

By contributing, you agree your contributions are licensed under the project's [MIT License](./LICENSE).
