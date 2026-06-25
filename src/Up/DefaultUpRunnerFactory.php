<?php

declare(strict_types=1);

namespace Deskhand\Up;

use Deskhand\Core\Capability\SystemCapabilityDetector;
use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Database\DefaultDatabaseProvisionerFactory;
use Deskhand\Core\Env\DotenvMaterializer;
use Deskhand\Core\Git\SystemGitRunner;
use Deskhand\Core\Gitignore\GitignoreManager;
use Deskhand\Core\Planning\WorktreePlanner;
use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Core\Registry\JsonRegistry;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Profile\Laravel\LaravelProfile;

/**
 * Wires the concrete implementations into an {@see UpRunner}. The repo root is
 * discovered best-effort so config (`deskhand.yaml`) and the registry resolve to
 * the right place; if the directory is not a repository, the runner's preflight
 * raises the friendly error.
 */
final class DefaultUpRunnerFactory implements UpRunnerFactory
{
    public function create(string $workingDirectory): UpRunner
    {
        $process = new SystemProcessRunner;
        $git = new SystemGitRunner($process);

        $repoRoot = $git->isGitRepository($workingDirectory)
            ? $git->repositoryRoot($workingDirectory)
            : $workingDirectory;

        $config = ConfigLoader::fromFile($repoRoot.'/deskhand.yaml');
        $registry = new JsonRegistry(JsonRegistry::pathFor($repoRoot));
        $capabilities = new SystemCapabilityDetector($process, $repoRoot);

        return new UpRunner(
            $git,
            $process,
            $registry,
            new DotenvMaterializer,
            $capabilities,
            new LaravelProfile($process, $config, $capabilities),
            new DefaultDatabaseProvisionerFactory,
            new WorktreePlanner($config, new UrlResolver($config), $registry),
            new GitignoreManager,
            $config,
        );
    }
}
