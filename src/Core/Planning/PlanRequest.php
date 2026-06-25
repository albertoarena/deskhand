<?php

declare(strict_types=1);

namespace Deskhand\Core\Planning;

/**
 * The per-run inputs the {@see WorktreePlanner} needs to derive a worktree
 * record: the branch and repo, the chosen engine and isolation flags, the
 * test-DB count, the optional path/url overrides, the parsed base `.env`, and
 * the creation timestamp (injected so planning stays deterministic/testable).
 */
final class PlanRequest
{
    /**
     * @param  array<string, string>  $baseEnv
     */
    public function __construct(
        public readonly string $branch,
        public readonly string $repoRoot,
        public readonly string $engine,
        public readonly bool $shared,
        public readonly bool $redisIsolated,
        public readonly int $testDbCount,
        public readonly ?string $pathFlag,
        public readonly ?string $urlFlag,
        public readonly array $baseEnv,
        public readonly string $createdAt,
    ) {}
}
