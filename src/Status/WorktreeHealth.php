<?php

declare(strict_types=1);

namespace Deskhand\Status;

use Deskhand\Core\Registry\WorktreeRecord;

/**
 * The health snapshot of a single managed worktree (§4.4): whether its
 * directory, env and database are present/reachable, whether its ports are in
 * use, and the resulting problem list.
 */
final class WorktreeHealth
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(
        public readonly WorktreeRecord $record,
        public readonly bool $worktreeExists,
        public readonly bool $envExists,
        public readonly bool $databaseReachable,
        public readonly bool $servePortInUse,
        public readonly bool $vitePortInUse,
        public readonly array $problems,
    ) {}

    public function healthy(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->record->slug,
            'branch' => $this->record->branch,
            'path' => $this->record->path,
            'worktree_exists' => $this->worktreeExists,
            'env_exists' => $this->envExists,
            'database_reachable' => $this->databaseReachable,
            'serve_port_in_use' => $this->servePortInUse,
            'vite_port_in_use' => $this->vitePortInUse,
            'healthy' => $this->healthy(),
            'problems' => $this->problems,
        ];
    }
}
