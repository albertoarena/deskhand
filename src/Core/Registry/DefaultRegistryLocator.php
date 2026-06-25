<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

use Deskhand\Core\Git\SystemGitRunner;
use Deskhand\Core\Process\SystemProcessRunner;

/**
 * Locates the JSON registry by resolving the repo root for the working
 * directory (falling back to the directory itself when it is not a repository).
 */
final class DefaultRegistryLocator implements RegistryLocator
{
    public function locate(string $workingDirectory): Registry
    {
        $git = new SystemGitRunner(new SystemProcessRunner);

        $repoRoot = $git->isGitRepository($workingDirectory)
            ? $git->repositoryRoot($workingDirectory)
            : $workingDirectory;

        return new JsonRegistry(JsonRegistry::pathFor($repoRoot));
    }
}
