<?php

declare(strict_types=1);

namespace Deskhand\Core\Git;

use Deskhand\Core\Process\ProcessRunner;
use Deskhand\Exception\DeskhandException;
use Deskhand\Exception\NotAGitRepositoryException;
use Deskhand\Exception\WorktreeExistsException;

/**
 * Concrete GitRunner: every git invocation goes through the {@see ProcessRunner}
 * seam, so this is the only git surface and it never shells out directly
 * (safety invariant #8). Commands are argv-style; failures are mapped to typed
 * exceptions carrying git's own stderr.
 */
final class SystemGitRunner implements GitRunner
{
    public function __construct(private readonly ProcessRunner $process) {}

    public function isGitRepository(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $result = $this->process->run(['git', 'rev-parse', '--is-inside-work-tree'], $directory);

        return $result->successful() && trim($result->stdout) === 'true';
    }

    public function repositoryRoot(string $directory): string
    {
        $result = $this->process->run(['git', 'rev-parse', '--show-toplevel'], $directory);

        if ($result->failed()) {
            throw new NotAGitRepositoryException("{$directory} is not inside a git repository.");
        }

        return trim($result->stdout);
    }

    public function branchExists(string $branch, string $repositoryRoot): bool
    {
        return $this->process->run(
            ['git', 'show-ref', '--verify', '--quiet', "refs/heads/{$branch}"],
            $repositoryRoot,
        )->successful();
    }

    public function addWorktree(string $repositoryRoot, string $path, string $branch, bool $createBranch): void
    {
        $command = $createBranch
            ? ['git', 'worktree', 'add', '-b', $branch, $path]
            : ['git', 'worktree', 'add', $path, $branch];

        $result = $this->process->run($command, $repositoryRoot);

        if ($result->failed()) {
            throw new WorktreeExistsException("Unable to add worktree at {$path}: ".trim($result->stderr));
        }
    }

    public function removeWorktree(string $repositoryRoot, string $path, bool $force = false): void
    {
        $command = ['git', 'worktree', 'remove'];

        if ($force) {
            $command[] = '--force';
        }

        $command[] = $path;

        $result = $this->process->run($command, $repositoryRoot);

        if ($result->failed()) {
            throw new DeskhandException("Unable to remove worktree at {$path}: ".trim($result->stderr));
        }
    }

    public function pruneWorktrees(string $repositoryRoot): void
    {
        $result = $this->process->run(['git', 'worktree', 'prune'], $repositoryRoot);

        if ($result->failed()) {
            throw new DeskhandException('Unable to prune worktrees: '.trim($result->stderr));
        }
    }

    public function listWorktrees(string $repositoryRoot): array
    {
        $result = $this->process->run(['git', 'worktree', 'list', '--porcelain'], $repositoryRoot);

        if ($result->failed()) {
            throw new DeskhandException('Unable to list worktrees: '.trim($result->stderr));
        }

        return $this->parseWorktrees($result->stdout);
    }

    public function removeBranch(string $branch, string $repositoryRoot, bool $force = false): void
    {
        $result = $this->process->run(
            ['git', 'branch', $force ? '-D' : '-d', $branch],
            $repositoryRoot,
        );

        if ($result->failed()) {
            throw new DeskhandException("Unable to remove branch {$branch}: ".trim($result->stderr));
        }
    }

    /**
     * Parse `git worktree list --porcelain`: records separated by blank lines,
     * each with `worktree <path>`, `HEAD <sha>`, and either `branch refs/heads/<name>`
     * or `detached`.
     *
     * @return list<GitWorktree>
     */
    private function parseWorktrees(string $output): array
    {
        $worktrees = [];

        foreach (preg_split('/(\r\n|\n|\r){2,}/', trim($output)) ?: [] as $block) {
            if (trim($block) === '') {
                continue;
            }

            $path = null;
            $branch = null;
            $head = null;

            foreach (preg_split('/\r\n|\n|\r/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'worktree ')) {
                    $path = substr($line, strlen('worktree '));
                } elseif (str_starts_with($line, 'HEAD ')) {
                    $head = substr($line, strlen('HEAD '));
                } elseif (str_starts_with($line, 'branch refs/heads/')) {
                    $branch = substr($line, strlen('branch refs/heads/'));
                }
            }

            if ($path !== null) {
                $worktrees[] = new GitWorktree($path, $branch, $head);
            }
        }

        return $worktrees;
    }
}
