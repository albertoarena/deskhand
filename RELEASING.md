# Releasing deskhand

This document defines how deskhand is versioned, what counts as a breaking
change, and the exact steps to cut a release. Releases are automated: pushing a
`v*` tag triggers the [`release`](./.github/workflows/release.yml) workflow, which
builds the PHAR and publishes a GitHub Release whose notes come from the
[changelog](./CHANGELOG.md).

## Versioning (Semantic Versioning)

deskhand follows [Semantic Versioning 2.0.0](https://semver.org): `MAJOR.MINOR.PATCH`.

Because deskhand is a **CLI, not a library**, its public API — the surface covered
by SemVer — is what users and scripts depend on:

- command names, arguments, and flags (`up`, `down`, `list`, `status`,
  `skill:install`);
- `deskhand.yaml` configuration keys and their default values;
- the `DESKHAND_*` environment variables injected into hooks;
- process exit codes;
- the on-disk registry format and location.

Internal PHP classes, namespaces, and interfaces are **not** part of the public
API and may change in any release.

| Bump | When |
|---|---|
| **MAJOR** | An incompatible change to the public surface: a removed/renamed command, flag, or config key; a changed exit-code meaning; a breaking change to the registry format. |
| **MINOR** | A backwards-compatible addition: a new command, flag, config key, engine, or capability. |
| **PATCH** | A backwards-compatible bug fix or internal change with no surface impact. |

Any change that weakens a safety invariant (see
[`docs/safety-model.md`](./docs/safety-model.md)) is treated as breaking,
regardless of the mechanical diff.

## The changelog

- We keep a human-written [`CHANGELOG.md`](./CHANGELOG.md) in
  [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.
- **Every user-facing change updates the `[Unreleased]` section in the same PR**,
  under the appropriate heading: `Added`, `Changed`, `Deprecated`, `Removed`,
  `Fixed`, or `Security`. Purely internal changes (refactors, tests, CI) need no
  entry.
- The changelog is the **source of truth for GitHub Release notes** — the release
  workflow extracts the section matching the tag, so write entries for users.

## Cutting a release

1. **Confirm `main` is green.** All required CI checks pass (Pest, PHPStan, Pint,
   `composer validate --strict` across the PHP 8.3/8.4/8.5 matrix). If `website/`
   changed, confirm the docs build.
2. **Roll the changelog.** In `CHANGELOG.md`:
   - rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` (ISO date, UTC);
   - add a fresh, empty `## [Unreleased]` section above it;
   - update the link references at the bottom:
     ```
     [Unreleased]: https://github.com/albertoarena/deskhand/compare/vX.Y.Z...HEAD
     [X.Y.Z]: https://github.com/albertoarena/deskhand/releases/tag/vX.Y.Z
     ```
3. **Commit** the changelog on `main`:
   ```bash
   git commit -am "chore: release vX.Y.Z"
   ```
4. **Tag** an annotated tag and push it with the commit:
   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin main --follow-tags
   ```
5. **Let CI publish.** The `release` workflow builds `build/deskhand.phar` with
   Box, verifies it runs `deskhand --version`, and attaches it to a new GitHub
   Release with the changelog section as the body.
6. **Verify.** Check the Release page, download the PHAR, and confirm
   `php deskhand.phar --version` prints `X.Y.Z`.

## Tag policy

- Tags are `vX.Y.Z` (leading `v`), **annotated**, and cut from `main` only.
- **Never move or delete a published tag.** A published release is immutable; fix
  a bad release by publishing the next patch version.
- Pre-releases use a SemVer suffix — `vX.Y.Z-rc.1`, `-beta.1` — and are marked as
  pre-releases on GitHub.

## First release

deskhand has not yet cut a tagged release; the accumulated work sits under
`[Unreleased]`. The first release will be **`v1.0.0`** — the v1 scope (a single
Laravel stack profile, per `docs/implementation.md`) is the 1.0 surface. Follow
the steps above, renaming `[Unreleased]` to `[1.0.0]`.
