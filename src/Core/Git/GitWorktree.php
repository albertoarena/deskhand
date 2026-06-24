<?php

declare(strict_types=1);

namespace Deskhand\Core\Git;

/**
 * A single git worktree as reported by `git worktree list`.
 */
final class GitWorktree
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $branch = null,
        public readonly ?string $head = null,
    ) {}
}
