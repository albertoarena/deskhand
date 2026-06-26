# Testing the Claude Code skill

The skill (`skill/SKILL.md`) is what teaches an agent when and how to use deskhand.
Testing it has a deterministic part (does it match the CLI?) and a behavioural part
(does an agent actually use it correctly?). Test it in three tiers.

## Tier 1 — static validation (automated, in CI)

`tests/Unit/Skill/SkillTest.php` asserts the skill documents every command and every
`up`/`down` flag, so it cannot silently drift from the real CLI. This runs in the
normal suite — no setup.

## Tier 2 — local dry-run (before publishing)

Catch trigger/wording problems before they reach users. The skill only loads if its
`description` matches the user's intent, so this is the highest-value manual check.

1. Scaffold a real Laravel app and install the skill into it:
   ```bash
   composer create-project laravel/laravel /tmp/skill-test/app
   cd /tmp/skill-test/app && git init -b main && git add -A && git commit -m init
   deskhand skill:install        # → .claude/skills/deskhand/SKILL.md
   ```
2. From that directory, run a Claude Code session — interactively, or headless for
   repeatability:
   ```bash
   claude -p "Set up an isolated environment for the branch feature/reports and run its tests."
   ```
3. Score the session against the rubric below.
4. Clean up with `deskhand down feature/reports --force` (or let the agent do it).

## Tier 3 — published, real project (final acceptance)

After release, in an actual project: install deskhand globally
(`composer global require albertoarena/deskhand`), run `deskhand skill:install`, and
drive a real agent session. This also validates the install instructions and the
published artifact — the gate your users actually hit.

## Rubric (Tier 2 / Tier 3)

A pass means the agent:

- [ ] **Triggered the skill** at all (it appeared in context / was used).
- [ ] Ran `deskhand up` rather than hand-rolling a worktree / database / ports.
- [ ] Ran deskhand **from the app directory** and worked **only inside the worktree**.
- [ ] Did **not** drop databases or delete worktrees manually.
- [ ] Cleaned up with `deskhand down --force` (non-interactive).

Then assert the mechanics on disk: the worktree and registry record were created
correctly during the run, and removed cleanly afterwards.

## Notes

- Tiers 2–3 are **acceptance-style**, not pass/fail unit tests: agent behaviour is
  non-deterministic. The static Tier-1 test carries the deterministic load.
- Headless `claude -p` runs **cost tokens** (tens of thousands per run) — reserve
  them for milestones, not every change.
