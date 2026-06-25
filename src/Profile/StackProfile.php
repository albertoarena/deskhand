<?php

declare(strict_types=1);

namespace Deskhand\Profile;

use Deskhand\Core\Registry\WorktreeRecord;

/**
 * The stack-specific provisioning/verification seam. v1 ships exactly one
 * implementation, LaravelProfile (Phase 4); the generic core depends only on
 * this interface.
 *
 * NOTE: this is the initial shape. Its method set is coupled to the `up`/`down`
 * orchestration (Phases 4–5) and may gain methods (e.g. an envaudit gate,
 * storage-symlink teardown) when those land. Changes here are expected and fine.
 */
interface StackProfile
{
    /** Stack identifier, e.g. "laravel". */
    public function name(): string;

    /**
     * Per-worktree `.env` overrides this profile contributes (DB connection,
     * APP_NAME tag, app URL, conditional Redis), given the resolved record, the
     * parsed base `.env` (for values like the base APP_NAME), and the project
     * directory name (the APP_NAME fallback when the base has none).
     *
     * @param  array<string, string>  $baseEnv
     * @return array<string, string>
     */
    public function envOverrides(WorktreeRecord $record, array $baseEnv, string $projectName): array;

    /**
     * Extra overrides forced into `.env.testing` (safe drivers: array
     * cache/session, sync queue), always applied regardless of runtime drivers.
     *
     * @return array<string, string>
     */
    public function testingEnvOverrides(): array;

    /** Generate a fresh app key in the worktree (run after dependencies install). */
    public function generateAppKey(string $worktreePath): void;

    /** Create required storage directories and the storage symlink. */
    public function provisionStorage(string $worktreePath): void;

    /** Run migrations against $databaseName (connection set via environment). */
    public function migrate(string $worktreePath, string $databaseName): void;

    /** Run the seeder. */
    public function seed(string $worktreePath): void;

    /** Run the verification suite; true only when it is green. */
    public function verify(string $worktreePath): bool;
}
