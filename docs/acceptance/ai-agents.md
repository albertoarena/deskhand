# Acceptance runbook — parallel AI agents

This procedure proves deskhand's real purpose: **multiple AI coding agents
working the same codebase in parallel, each in an isolated worktree, without
colliding** on files, databases, or ports.

It is a *manual milestone check*, not a routine one — it spends real agent
tokens. For routine verification use the scripted harness instead
([`scripts/acceptance/parallel-worktrees.sh`](../../scripts/acceptance/parallel-worktrees.sh)).

**Rough cost:** ~100–200k agent tokens and ~15–25 minutes for 3 agents (the
agents run in parallel; wall time is dominated by their reasoning, not deskhand).

## Steps

1. **Scaffold a real Laravel app and commit it.**
   ```bash
   composer create-project laravel/laravel /tmp/deskhand-demo/app
   cd /tmp/deskhand-demo/app
   git init -b main && git add -A && git commit -m "init laravel"
   ```

2. **Provision one isolated worktree per agent.** Run deskhand *from the app
   directory* (it resolves the repo from the current directory).
   ```bash
   deskhand up agent/billing
   deskhand up agent/payments
   deskhand up agent/reporting
   deskhand list          # note the distinct ports / DBs / URLs
   ```

3. **Launch the agents in parallel — one per worktree.** Give each agent the
   **absolute path of its worktree** and a small, self-contained task, and
   instruct it to work **only** inside that path (no composer, no `.env` edits,
   no migrations). Worktree paths are `.claude/worktrees/<slug>` under the app,
   e.g. `…/app/.claude/worktrees/agent-billing`.

   A good task per agent: add a route + a Feature test, then run just that test
   (`php artisan test --filter=<Name>`).

4. **Verify isolation** once the agents finish:
   - the base app's `routes/web.php` has **none** of the agents' changes;
   - each worktree contains **only its own** route + test (`git -C <wt> status`);
   - ports / databases / `APP_NAME` tags differ across worktrees (`deskhand list`);
   - every agent's test passed.

5. **Tear down** — only deskhand's artifacts are removed:
   ```bash
   deskhand down agent/billing  --force
   deskhand down agent/payments --force
   deskhand down agent/reporting --force
   deskhand list   # empty
   ```

## Pitfalls

- **Always run deskhand from the app directory.** It operates on the git repo
  found from the current directory; running it elsewhere targets the wrong repo.
- **Scope each agent to its worktree path.** Agents should never touch sibling
  worktrees or the base app.
