<?php

declare(strict_types=1);

namespace Deskhand\Down;

use Deskhand\Core\Database\DefaultDatabaseProvisionerFactory;
use Deskhand\Core\Env\DotenvMaterializer;
use Deskhand\Core\Git\SystemGitRunner;
use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Core\Registry\JsonRegistry;

/**
 * Wires the concrete implementations into a {@see DownRunner}, resolving the
 * repo root so the registry is read from the right place.
 */
final class DefaultDownRunnerFactory implements DownRunnerFactory
{
    public function create(string $workingDirectory): DownRunner
    {
        $process = new SystemProcessRunner;
        $git = new SystemGitRunner($process);

        $repoRoot = $git->isGitRepository($workingDirectory)
            ? $git->repositoryRoot($workingDirectory)
            : $workingDirectory;

        return new DownRunner(
            $git,
            new JsonRegistry(JsonRegistry::pathFor($repoRoot)),
            new DefaultDatabaseProvisionerFactory,
            new DotenvMaterializer,
            $repoRoot,
        );
    }
}
