<?php

declare(strict_types=1);

namespace Deskhand\Core\Capability;

/**
 * Probes the host and project for the capabilities `up` needs (§8). Detect,
 * never assume; the orchestration fails early with a clear message when a
 * required capability is missing.
 */
interface CapabilityDetector
{
    public function hasComposer(): bool;

    public function hasNpm(): bool;

    public function hasYarn(): bool;

    public function hasMysqlClient(): bool;

    /** A frontend is present when the project has a package.json. */
    public function hasFrontend(string $projectPath): bool;

    /** Resolve the JS package manager from the lockfile: 'npm' | 'yarn' | null. */
    public function detectPackageManager(string $projectPath): ?string;

    /** Whether parallel testing (paratest) is available, for the --parallel fallback. */
    public function hasParallelTesting(string $projectPath): bool;

    public function needsStorageLink(string $projectPath): bool;
}
