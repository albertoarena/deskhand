# Acceptance harnesses

Two complementary ways to prove deskhand's parallel-worktree isolation against a
**real Laravel app** — one cheap and automated, one realistic and manual.

## 1. Scripted harness (routine, ~free)

[`parallel-worktrees.sh`](./parallel-worktrees.sh) scaffolds a fresh Laravel app,
provisions N isolated worktrees with `deskhand up`, runs a deterministic workload
(add a route + a test, then run the suite) **concurrently** in each, asserts
isolation (distinct databases, distinct serve ports, base app untouched, one
route per worktree), and tears everything down with `deskhand down`.

```bash
scripts/acceptance/parallel-worktrees.sh        # 3 workers (default)
WORKERS=5 scripts/acceptance/parallel-worktrees.sh
KEEP=1 scripts/acceptance/parallel-worktrees.sh # keep the scratch app to inspect
```

Requires `composer` and `php` on `PATH`. Takes a few minutes (composer-bound);
prints `ACCEPTANCE: PASS` on success and is safe to re-run after any change.

## 2. AI sub-agent runbook (milestone, ~150k tokens)

[`../../docs/acceptance/ai-agents.md`](../../docs/acceptance/ai-agents.md) is a
manual procedure for driving **real AI coding agents** in parallel worktrees —
the actual use case deskhand exists for. Use it at milestones, not routinely.

## 3. Skill dry-run harness (Tier-2 skill test)

[`skill-dryrun.sh`](./skill-dryrun.sh) automates everything *around* a Claude Code
skill test: it puts `deskhand` on `PATH`, scaffolds a Laravel app, and installs the
skill into it — then hands off to you (or a headless `claude -p` run) to drive the
session, and verifies/cleans up afterwards.

```bash
scripts/acceptance/skill-dryrun.sh setup       # prepare app + skill, print the prompt
scripts/acceptance/skill-dryrun.sh setup --headless   # also run `claude -p`
scripts/acceptance/skill-dryrun.sh verify      # show deskhand list/status
scripts/acceptance/skill-dryrun.sh teardown    # remove worktrees, app and PATH link
```

See [`../../docs/acceptance/skill-testing.md`](../../docs/acceptance/skill-testing.md)
for the three-tier plan and the rubric to score the session against.
