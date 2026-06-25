<?php

declare(strict_types=1);

namespace Deskhand\Down;

use Deskhand\Core\Database\DatabaseProvisionerFactory;
use Deskhand\Core\Env\EnvMaterializer;
use Deskhand\Core\Git\GitRunner;
use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\WorktreeRecord;
use Throwable;

/**
 * Tears down a worktree environment (§4.2), removing **only** what deskhand
 * recorded as creating. This is where the cardinal safety invariant lives:
 * teardown acts solely on the registry record — never on names derived from a
 * slug — and never drops a shared (base project) database. Every step is
 * best-effort and independently guarded, so a half-provisioned worktree still
 * cleans up; the registry entry is removed last.
 */
final class DownRunner
{
    public function __construct(
        private readonly GitRunner $git,
        private readonly Registry $registry,
        private readonly DatabaseProvisionerFactory $provisioners,
        private readonly EnvMaterializer $env,
        private readonly string $repoRoot,
    ) {}

    public function find(string $slugOrBranch): ?WorktreeRecord
    {
        return $this->registry->find($slugOrBranch);
    }

    /**
     * @param  (callable(string): void)|null  $notify
     */
    public function tearDown(WorktreeRecord $record, bool $keepBranch, ?callable $notify = null): DownResult
    {
        $notify ??= static fn (string $message): null => null;

        $worktreePath = $this->absolutePath($record->path);
        $warnings = [];

        // 2. Drop only the databases this record lists as deskhand-created.
        // A shared (base project) database is never dropped.
        $dropped = [];

        if ($record->db->shared) {
            $notify('database: skipped shared base database (not deskhand-created)');
        } else {
            $provisioner = $this->provisioners->for(
                $record->db->engine,
                $worktreePath,
                $this->env->read($this->repoRoot.'/.env'),
            );

            foreach ([$record->db->main, ...$record->db->testDbs] as $database) {
                try {
                    $provisioner->drop($database);
                    $dropped[] = $database;
                    $notify("database: dropped {$database}");
                } catch (Throwable $e) {
                    $warnings[] = "could not drop database {$database}: {$e->getMessage()}";
                }
            }
        }

        // 3. Remove the storage symlink as a link (never follow into the target).
        $link = $worktreePath.'/public/storage';

        if (is_link($link)) {
            if (unlink($link)) {
                $notify('storage: removed symlink');
            } else {
                $warnings[] = 'could not remove storage symlink';
            }
        }

        // 4. Remove the worktree, prune orphaned refs, remove the branch.
        try {
            $this->git->removeWorktree($this->repoRoot, $worktreePath, force: true);
            $notify('worktree: removed');
        } catch (Throwable $e) {
            $warnings[] = "could not remove worktree: {$e->getMessage()}";
        }

        try {
            $this->git->pruneWorktrees($this->repoRoot);
        } catch (Throwable $e) {
            $warnings[] = "could not prune worktrees: {$e->getMessage()}";
        }

        $branchRemoved = false;

        if (! $keepBranch) {
            try {
                $this->git->removeBranch($record->branch, $this->repoRoot);
                $branchRemoved = true;
                $notify("branch: removed {$record->branch}");
            } catch (Throwable $e) {
                $warnings[] = "branch {$record->branch} not removed ({$e->getMessage()}); it may have unmerged work — use 'git branch -D' to force";
            }
        }

        // 6. Remove the registry entry last, so an interrupted teardown is retryable.
        $this->registry->remove($record->slug);
        $notify('registry: entry removed');

        return new DownResult($record->slug, $branchRemoved, $dropped, $warnings);
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->repoRoot.'/'.$path;
    }
}
