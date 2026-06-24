<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Git\GitRunner;
use Deskhand\Core\Git\GitWorktree;

final class FakeGitRunner implements GitRunner
{
    public bool $isRepository = true;

    /** @var list<string> */
    private array $branches = [];

    /** @var array<string, list<GitWorktree>> keyed by repository root */
    private array $worktrees = [];

    public function registerBranch(string $branch): void
    {
        $this->branches[] = $branch;
    }

    public function isGitRepository(string $directory): bool
    {
        return $this->isRepository;
    }

    public function repositoryRoot(string $directory): string
    {
        return $directory;
    }

    public function branchExists(string $branch, string $repositoryRoot): bool
    {
        return in_array($branch, $this->branches, true);
    }

    public function addWorktree(string $repositoryRoot, string $path, string $branch, bool $createBranch): void
    {
        $this->worktrees[$repositoryRoot][] = new GitWorktree($path, $branch);

        if ($createBranch) {
            $this->branches[] = $branch;
        }
    }

    public function removeWorktree(string $repositoryRoot, string $path, bool $force = false): void
    {
        $this->worktrees[$repositoryRoot] = array_values(array_filter(
            $this->worktrees[$repositoryRoot] ?? [],
            fn (GitWorktree $worktree): bool => $worktree->path !== $path,
        ));
    }

    public function pruneWorktrees(string $repositoryRoot): void
    {
        // no-op for the fake
    }

    public function listWorktrees(string $repositoryRoot): array
    {
        return $this->worktrees[$repositoryRoot] ?? [];
    }

    public function removeBranch(string $branch, string $repositoryRoot, bool $force = false): void
    {
        $this->branches = array_values(array_filter(
            $this->branches,
            fn (string $existing): bool => $existing !== $branch,
        ));
    }
}
