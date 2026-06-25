<?php

declare(strict_types=1);

namespace Deskhand\Status;

use Deskhand\Core\Database\DatabaseProvisionerFactory;
use Deskhand\Core\Env\EnvMaterializer;
use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\WorktreeRecord;

/**
 * Computes the health of managed worktrees (§4.4) from the registry: directory
 * present, `.env` present, database reachable, and ports in use. Read-only — it
 * inspects, never mutates.
 */
final class StatusRunner
{
    public function __construct(
        private readonly Registry $registry,
        private readonly DatabaseProvisionerFactory $provisioners,
        private readonly EnvMaterializer $env,
        private readonly PortChecker $ports,
        private readonly string $repoRoot,
    ) {}

    /**
     * @return list<WorktreeHealth>
     */
    public function all(): array
    {
        return array_map(fn (WorktreeRecord $record): WorktreeHealth => $this->check($record), $this->registry->all());
    }

    public function one(string $slugOrBranch): ?WorktreeHealth
    {
        $record = $this->registry->find($slugOrBranch);

        return $record === null ? null : $this->check($record);
    }

    private function check(WorktreeRecord $record): WorktreeHealth
    {
        $worktreePath = $this->absolutePath($record->path);

        $worktreeExists = is_dir($worktreePath);
        $envExists = is_file($worktreePath.'/.env');
        $databaseReachable = $this->databaseReachable($record, $worktreePath);

        $problems = [];

        if (! $worktreeExists) {
            $problems[] = 'worktree directory missing';
        }

        if (! $envExists) {
            $problems[] = '.env missing';
        }

        if (! $databaseReachable) {
            $problems[] = 'database unreachable or missing';
        }

        return new WorktreeHealth(
            record: $record,
            worktreeExists: $worktreeExists,
            envExists: $envExists,
            databaseReachable: $databaseReachable,
            servePortInUse: $this->ports->isInUse($record->ports->serve),
            vitePortInUse: $this->ports->isInUse($record->ports->vite),
            problems: $problems,
        );
    }

    private function databaseReachable(WorktreeRecord $record, string $worktreePath): bool
    {
        $provisioner = $this->provisioners->for(
            $record->db->engine,
            $worktreePath,
            $this->env->read($this->repoRoot.'/.env'),
        );

        return $provisioner->canConnect() && $provisioner->exists($record->db->main);
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->repoRoot.'/'.$path;
    }
}
