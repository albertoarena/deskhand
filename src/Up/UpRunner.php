<?php

declare(strict_types=1);

namespace Deskhand\Up;

use Deskhand\Core\Capability\CapabilityDetector;
use Deskhand\Core\Config\Config;
use Deskhand\Core\Database\DatabaseProvisionerFactory;
use Deskhand\Core\Env\EnvMaterializer;
use Deskhand\Core\Git\GitRunner;
use Deskhand\Core\Gitignore\GitignoreManager;
use Deskhand\Core\Naming\DatabaseNamer;
use Deskhand\Core\Planning\PlanRequest;
use Deskhand\Core\Planning\WorktreePlanner;
use Deskhand\Core\Process\ProcessRunner;
use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Exception\DatabaseProvisionException;
use Deskhand\Exception\DeskhandException;
use Deskhand\Exception\MissingCapabilityException;
use Deskhand\Exception\NotAGitRepositoryException;
use Deskhand\Exception\VerificationFailedException;
use Deskhand\Profile\StackProfile;

/**
 * Orchestrates `deskhand up` (§4.1): wires the subsystems together in order,
 * upholding the safety invariants (record before create-effect; idempotent
 * repair; never derive what to drop). All side effects go through the injected
 * seams, so the whole flow is unit-tested with fakes.
 */
final class UpRunner
{
    public function __construct(
        private readonly GitRunner $git,
        private readonly ProcessRunner $process,
        private readonly Registry $registry,
        private readonly EnvMaterializer $env,
        private readonly CapabilityDetector $capabilities,
        private readonly StackProfile $profile,
        private readonly DatabaseProvisionerFactory $provisioners,
        private readonly WorktreePlanner $planner,
        private readonly GitignoreManager $gitignore,
        private readonly Config $config,
    ) {}

    /**
     * @param  (callable(string): void)|null  $notify  progress line emitter
     */
    public function run(UpRequest $request, ?callable $notify = null): UpResult
    {
        $notify ??= static fn (string $message): null => null;

        // 1. Preflight.
        if (! $this->git->isGitRepository($request->workingDirectory)) {
            throw new NotAGitRepositoryException("{$request->workingDirectory} is not inside a git repository.");
        }

        $repoRoot = $this->git->repositoryRoot($request->workingDirectory);

        if (! $this->capabilities->hasComposer()) {
            throw new MissingCapabilityException('composer is required but was not found on PATH.');
        }

        $gitignoreAdded = $this->gitignore->ensure($repoRoot);

        foreach ($gitignoreAdded as $line) {
            $notify("gitignore: added {$line}");
        }

        $baseEnv = $this->env->read($repoRoot.'/.env');

        // 2. Resolve slug & derived names (collision check lives in the planner).
        $record = $this->planner->plan(new PlanRequest(
            branch: $request->branch,
            repoRoot: $repoRoot,
            engine: $request->engine,
            shared: $request->shared,
            redisIsolated: $this->resolveRedisIsolation($request, $baseEnv),
            testDbCount: $request->engine === DatabaseNamer::ENGINE_MYSQL && ! $request->shared
                ? $this->detectCpuCount($repoRoot)
                : 0,
            pathFlag: $request->pathFlag,
            urlFlag: $request->urlFlag,
            baseEnv: $baseEnv,
            createdAt: $request->createdAt,
        ));

        $worktreePath = $this->absolutePath($repoRoot, $record->path);

        // 3. Create the worktree (idempotent: reuse an existing one).
        if ($this->worktreeExists($repoRoot, $worktreePath)) {
            $notify("worktree: reusing {$record->path}");
        } else {
            $this->git->addWorktree(
                $repoRoot,
                $worktreePath,
                $request->branch,
                createBranch: ! $this->git->branchExists($request->branch, $repoRoot),
            );
            $notify("worktree: created at {$record->path}");
        }

        // Record before create-effects persist (safety invariant #2).
        $this->registry->save($record);

        // 4. Materialize env (copy, never symlink) for .env and .env.testing.
        $overrides = $this->profile->envOverrides($record, $baseEnv, basename($repoRoot));
        $this->env->writeEnv($repoRoot.'/.env', $worktreePath.'/.env', $overrides);
        $this->env->writeEnv(
            $repoRoot.'/.env',
            $worktreePath.'/.env.testing',
            array_merge($overrides, $this->profile->testingEnvOverrides()),
        );
        $notify('env: materialized .env and .env.testing');

        // 5. Dependencies.
        $composer = $this->process->run(['composer', 'install', '--no-interaction'], $worktreePath);

        if ($composer->failed()) {
            throw new DeskhandException('composer install failed: '.trim($composer->stderr));
        }

        $notify('composer: installed');
        $packageManager = $this->installFrontend($worktreePath, $notify);

        // 6. APP_KEY. 7. Storage.
        $this->profile->generateAppKey($worktreePath);
        $notify('app key: generated');
        $this->profile->provisionStorage($worktreePath);
        $notify('storage: provisioned');

        // 8. Database (skipped entirely under --shared-db).
        if ($request->shared) {
            $notify('database: using shared base database (read-only)');
        } else {
            $this->provisionDatabases($request, $record, $worktreePath, $baseEnv, $notify);
        }

        // 9. Migrate & seed (skipped under --shared-db).
        if ($request->shared) {
            $notify('database: skipped migrate and seed (shared, read-only)');
        } else {
            $this->profile->migrate($worktreePath, $record->db->main);

            foreach ($record->db->testDbs as $testDb) {
                $this->profile->migrate($worktreePath, $testDb);
            }

            $notify('database: migrated');

            if ($this->config->seed) {
                $this->profile->seed($worktreePath);
                $notify('database: seeded');
            }
        }

        // 10. post_up_hooks (verbatim, with the DESKHAND_* facts).
        $deskhandEnv = $this->deskhandEnv($record);

        foreach ($this->config->postUpHooks as $hook) {
            $result = $this->process->runShell($hook, $worktreePath, $deskhandEnv);

            if ($result->failed()) {
                throw new DeskhandException("post-up hook failed ({$hook}): ".trim($result->stderr));
            }

            $notify("hook: {$hook}");
        }

        // 11. envaudit gate.
        $envauditSkipped = $this->runEnvaudit($request, $worktreePath, $deskhandEnv, $notify);

        // 12. Verify.
        [$verified, $verifySkipped] = $this->verify($request, $worktreePath, $notify);

        // 13. Finalize registry & report.
        $this->registry->save($record);

        return new UpResult(
            record: $record,
            gitignoreAdded: $gitignoreAdded,
            sharedDb: $request->shared,
            verified: $verified,
            verifySkipped: $verifySkipped,
            envauditSkipped: $envauditSkipped,
            packageManager: $packageManager,
        );
    }

    /**
     * @param  array<string, string>  $baseEnv
     * @param  callable(string): void  $notify
     */
    private function provisionDatabases(UpRequest $request, WorktreeRecord $record, string $worktreePath, array $baseEnv, callable $notify): void
    {
        $provisioner = $this->provisioners->for($request->engine, $worktreePath, $baseEnv);

        if ($request->engine === DatabaseNamer::ENGINE_MYSQL && ! $provisioner->canConnect()) {
            throw new DatabaseProvisionException("Cannot connect to MySQL using the project's .env credentials.");
        }

        $provisioner->create($record->db->main);

        foreach ($record->db->testDbs as $testDb) {
            $provisioner->create($testDb);
        }

        $notify("database: created {$record->db->main}");
    }

    /**
     * @param  callable(string): void  $notify
     */
    private function installFrontend(string $worktreePath, callable $notify): ?string
    {
        if ($this->config->frontendInstall === 'false' || ! $this->capabilities->hasFrontend($worktreePath)) {
            return null;
        }

        $manager = $this->config->jsPackageManager === 'auto'
            ? ($this->capabilities->detectPackageManager($worktreePath) ?? 'npm')
            : $this->config->jsPackageManager;

        if ($manager === 'npm' && ! $this->capabilities->hasNpm()) {
            throw new MissingCapabilityException('A frontend was detected but npm was not found on PATH.');
        }

        if ($manager === 'yarn' && ! $this->capabilities->hasYarn()) {
            throw new MissingCapabilityException('A frontend was detected but yarn was not found on PATH.');
        }

        $result = $this->process->run($this->frontendCommand($manager, $worktreePath), $worktreePath);

        if ($result->failed()) {
            throw new DeskhandException("{$manager} install failed: ".trim($result->stderr));
        }

        $notify("frontend: installed with {$manager}");

        return $manager;
    }

    /**
     * @return list<string>
     */
    private function frontendCommand(string $manager, string $worktreePath): array
    {
        if ($manager === 'yarn') {
            return is_file($worktreePath.'/yarn.lock')
                ? ['yarn', 'install', '--frozen-lockfile']
                : ['yarn', 'install'];
        }

        return is_file($worktreePath.'/package-lock.json')
            ? ['npm', 'ci', '--prefer-offline']
            : ['npm', 'install', '--prefer-offline'];
    }

    /**
     * @param  array<string, string>  $deskhandEnv
     * @param  callable(string): void  $notify
     * @return bool whether the gate was skipped because envaudit is absent
     */
    private function runEnvaudit(UpRequest $request, string $worktreePath, array $deskhandEnv, callable $notify): bool
    {
        if ($request->skipEnvaudit) {
            return false;
        }

        $binary = $worktreePath.'/vendor/bin/envaudit';

        if (! is_file($binary)) {
            $notify('envaudit: not installed — skipping the env gate (composer require --dev albertoarena/envaudit to enable, or pass --no-envaudit)');

            return true;
        }

        $result = $this->process->run([$binary], $worktreePath, $deskhandEnv);

        if ($result->failed()) {
            throw new DeskhandException('envaudit reported errors:'."\n".trim($result->stdout."\n".$result->stderr));
        }

        $notify('envaudit: passed');

        return false;
    }

    /**
     * @param  callable(string): void  $notify
     * @return array{0: bool, 1: bool} [verified, skipped]
     */
    private function verify(UpRequest $request, string $worktreePath, callable $notify): array
    {
        if ($request->skipVerify) {
            $notify('verify: skipped');

            return [false, true];
        }

        if (! $this->profile->verify($worktreePath)) {
            throw new VerificationFailedException('The verification suite failed.');
        }

        $notify('verify: suite green');

        return [true, false];
    }

    /**
     * @param  array<string, string>  $baseEnv
     */
    private function resolveRedisIsolation(UpRequest $request, array $baseEnv): bool
    {
        if ($request->skipRedisIsolation) {
            return false;
        }

        return match ($this->config->redisIsolation) {
            'true' => true,
            'false' => false,
            default => $this->detectsRedis($baseEnv),
        };
    }

    /**
     * @param  array<string, string>  $baseEnv
     */
    private function detectsRedis(array $baseEnv): bool
    {
        foreach (['CACHE_STORE', 'CACHE_DRIVER', 'QUEUE_CONNECTION', 'SESSION_DRIVER', 'BROADCAST_CONNECTION'] as $key) {
            if (($baseEnv[$key] ?? null) === 'redis') {
                return true;
            }
        }

        return false;
    }

    private function detectCpuCount(string $repoRoot): int
    {
        foreach ([['nproc'], ['sysctl', '-n', 'hw.ncpu']] as $command) {
            $result = $this->process->run($command, $repoRoot);
            $value = trim($result->stdout);

            if ($result->successful() && ctype_digit($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return 4;
    }

    /**
     * @return array<string, string>
     */
    private function deskhandEnv(WorktreeRecord $record): array
    {
        return [
            'DESKHAND_SLUG' => $record->slug,
            'DESKHAND_BRANCH' => $record->branch,
            'DESKHAND_PATH' => $record->path,
            'DESKHAND_URL' => $record->url,
            'DESKHAND_SERVE_PORT' => (string) $record->ports->serve,
            'DESKHAND_VITE_PORT' => (string) $record->ports->vite,
            'DESKHAND_DB_ENGINE' => $record->db->engine,
            'DESKHAND_DB_NAME' => $record->db->main,
        ];
    }

    private function worktreeExists(string $repoRoot, string $worktreePath): bool
    {
        $target = realpath($worktreePath) ?: $worktreePath;

        foreach ($this->git->listWorktrees($repoRoot) as $worktree) {
            if ((realpath($worktree->path) ?: $worktree->path) === $target) {
                return true;
            }
        }

        return false;
    }

    private function absolutePath(string $repoRoot, string $path): string
    {
        return str_starts_with($path, '/') ? $path : $repoRoot.'/'.$path;
    }
}
