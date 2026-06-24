<?php

declare(strict_types=1);

namespace Deskhand\Core\Env;

/**
 * Reads a base `.env` and writes per-worktree env files with overrides merged
 * in. Generic key/value handling only — Laravel-specific overrides (safe test
 * drivers, etc.) are supplied by the caller. Always copies, never symlinks
 * (safety invariant #5).
 */
interface EnvMaterializer
{
    /** @return array<string, string> */
    public function read(string $envPath): array;

    /**
     * Write $targetPath from $baseEnvPath with $overrides merged over it
     * (existing keys replaced, new keys appended).
     *
     * @param  array<string, string>  $overrides
     */
    public function writeEnv(string $baseEnvPath, string $targetPath, array $overrides): void;
}
