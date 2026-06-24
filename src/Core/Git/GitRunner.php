<?php

declare(strict_types=1);

namespace Deskhand\Core\Git;

/**
 * Git operations deskhand needs. The concrete implementation is the only place
 * that shells out to `git`.
 */
interface GitRunner
{
    public function isGitRepository(string $directory): bool;

    /** Absolute path to the repository root containing $directory. */
    public function repositoryRoot(string $directory): string;

    public function branchExists(string $branch, string $repositoryRoot): bool;

    public function addWorktree(string $repositoryRoot, string $path, string $branch, bool $createBranch): void;

    public function removeWorktree(string $repositoryRoot, string $path, bool $force = false): void;

    public function pruneWorktrees(string $repositoryRoot): void;

    /** @return list<GitWorktree> */
    public function listWorktrees(string $repositoryRoot): array;

    public function removeBranch(string $branch, string $repositoryRoot, bool $force = false): void;
}
