# CLAUDE.md

Guidance for Claude Code working in the **deskhand** repository.

## What this project is

`deskhand` is a stack-agnostic PHP CLI that turns a bare `git worktree` into a fully runnable, test-passing, isolated Laravel environment — so multiple AI coding agents (or humans) can work the same codebase in parallel without colliding on files, databases, or ports.

The authoritative specification is **[`docs/implementation.md`](./docs/implementation.md)**. It is prescriptive: implement exactly what it specifies — command names, flags, config keys, interfaces, file paths, and safety invariants are fixed. Do not redesign the architecture or rename agreed surfaces. If something is genuinely ambiguous, ask rather than guess.

Supporting design docs:
- [`docs/architecture.md`](./docs/architecture.md) — the shape and reasoning.
- [`docs/safety-model.md`](./docs/safety-model.md) — the guarantees and invariants.
- [`docs/isolation-model.md`](./docs/isolation-model.md) — what gets isolated and how.

## Non-negotiables

1. **TDD is mandatory.** Write Pest tests first, from the specified behaviour, before implementing. The behaviour is already defined in `docs/implementation.md`.
2. **Safety invariants (see `docs/safety-model.md`) are hard rules.** Above all: **never drop a database deskhand did not create.** `down` acts only on the registry; no registry record → drop nothing; never derive DB names from a slug for deletion. This invariant must always have an explicit, named test.
3. **All side effects sit behind interfaces.** `GitRunner`, `ProcessRunner`, `DatabaseProvisioner`, `Registry`, `EnvMaterializer`, `CapabilityDetector`, `StackProfile`. Orchestration depends on interfaces; only the concrete implementations touch git/processes/filesystem/DB. **No `exec`/`shell_exec`/`proc_open` anywhere else.**
4. **Generic core stays Laravel-free.** Laravel logic lives only in `LaravelProfile`. Ship only the Laravel profile in v1, but keep the `StackProfile` seam clean.
5. **PHP floor is 8.3.** `composer.json` requires `^8.3`; the CLI guards the running PHP version at startup with a friendly message, never a stack trace.
6. **Public-history hygiene.** No real secrets, credentials, or machine-specific absolute paths in code or fixtures — the repo goes public and full history is exposed. Fixtures use placeholders only.

## Conventions

- **Language/framework:** PHP 8.3+, Symfony Console.
- **Tests:** Pest. Unit tests use fakes (in `tests/Fakes/`) for every interface; a thin integration layer exercises real implementations against a temp git repo + SQLite.
- **Static analysis:** PHPStan (no Larastan — deskhand is framework-free and shells out to artisan, so there's no Laravel magic to analyze). Do not lower the level to silence real findings.
- **Style:** Laravel Pint. Run `pint` before committing.
- **Distribution:** global Composer tool + PHAR (Box). deskhand carries its own dependencies and never relies on the target project's autoloader.

## How to run the checks

```bash
composer install
vendor/bin/pest            # tests
vendor/bin/phpstan analyse # static analysis
vendor/bin/pint            # fix style
vendor/bin/pint --test     # check style (CI mode)
composer validate --strict
```

## Acceptance testing (real Laravel, end-to-end)

To validate any important change against a real Laravel app — not just the unit
suite — use the harnesses in `scripts/acceptance/` (see its `README.md`):

```bash
scripts/acceptance/parallel-worktrees.sh   # scaffolds Laravel, provisions N
                                           # isolated worktrees, runs a workload
                                           # concurrently, asserts isolation,
                                           # tears down. Prints ACCEPTANCE: PASS.
```

- For the real parallel-AI-agents use case, follow `docs/acceptance/ai-agents.md`.
- A gated Pest round-trip also exists: `DESKHAND_TEST_LARAVEL=1 vendor/bin/pest`.
- **Pitfall:** deskhand resolves the repo from the current directory — always run
  it (and these harnesses' provisioning) from *inside the target app*, or it will
  act on the wrong repo.

## Build order

Follow the sequence in `docs/implementation.md` §16: skeleton → interfaces + fakes → core subsystems (test-first) → concrete git/process impls → `LaravelProfile` → commands (test-first, with the safety tests on `down`) → integration round-trip → CI → repo deliverables → skill → docs site → logo (last).

## Out of scope (do not build)

Any non-Laravel profile; Windows support; Redis isolation by default (conditional only); MariaDB; cloud/preview environments; the orchestration/merge layer. See `docs/implementation.md` §17.

## Git Commit Conventions

### Format
- type: short subject line (max 50 chars)
- Detailed body paragraph explaining what and why (not how).

### Rules
- No Claude attribution - NEVER include "Generated with Claude Code" or "Co-Authored-By: Claude"
- Keep first line under 50 characters
- Use heredoc for multi-line commit messages
