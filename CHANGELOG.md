# Changelog

All notable changes to deskhand are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
See [RELEASING.md](./RELEASING.md) for what counts as a breaking change and how a
release is cut.

## [Unreleased]

### Added

- `deskhand up <branch>` — provision a fully isolated, verified Laravel
  environment for a worktree: its own database, deterministic slug-derived ports,
  a copied `.env` and `.env.testing` with a fresh `APP_KEY`, dependency installs,
  storage link, migrations, an optional envaudit gate, and a Pest verification run
  that must be green. Flags: `--path`, `--db=sqlite|mysql`, `--shared-db`,
  `--url`, `--no-envaudit`, `--no-redis-isolation`, `--no-verify`.
- `deskhand down <branch|slug>` — safe teardown that removes **only** what the
  registry records as deskhand-created. Flags: `--keep-branch`, `--force`.
- `deskhand list` — tabular (or `--json`) overview of managed worktrees.
- `deskhand status [<branch|slug>]` — health checks for managed worktrees
  (`--json` supported).
- `deskhand skill:install` — install the Claude Code skill into a project or, with
  `--global`, for the user.
- Full database isolation: SQLite (default) and MySQL engines, with conditional
  per-worktree Redis namespacing.
- `deskhand.yaml` configuration with zero-config defaults, plus `DESKHAND_*`
  environment facts injected into hooks and commands.
- Claude Code skill (`skill/SKILL.md`).
- Distribution as a global Composer tool and a standalone PHAR (Box), attached to
  GitHub Releases.
- Documentation site (Astro Starlight) at <https://albertoarena.github.io/deskhand>.

[Unreleased]: https://github.com/albertoarena/deskhand/commits/main
