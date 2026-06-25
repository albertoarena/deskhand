<?php

declare(strict_types=1);

namespace Deskhand\Status;

use Deskhand\Core\Database\DefaultDatabaseProvisionerFactory;
use Deskhand\Core\Env\DotenvMaterializer;
use Deskhand\Core\Git\SystemGitRunner;
use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Core\Registry\JsonRegistry;

/**
 * Wires the concrete implementations into a {@see StatusRunner}, resolving the
 * repo root so the registry and worktree paths are read from the right place.
 */
final class DefaultStatusRunnerFactory implements StatusRunnerFactory
{
    public function create(string $workingDirectory): StatusRunner
    {
        $git = new SystemGitRunner(new SystemProcessRunner);

        $repoRoot = $git->isGitRepository($workingDirectory)
            ? $git->repositoryRoot($workingDirectory)
            : $workingDirectory;

        return new StatusRunner(
            new JsonRegistry(JsonRegistry::pathFor($repoRoot)),
            new DefaultDatabaseProvisionerFactory,
            new DotenvMaterializer,
            new SocketPortChecker,
            $repoRoot,
        );
    }
}
