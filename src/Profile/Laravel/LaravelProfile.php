<?php

declare(strict_types=1);

namespace Deskhand\Profile\Laravel;

use Deskhand\Core\Capability\CapabilityDetector;
use Deskhand\Core\Config\Config;
use Deskhand\Core\Naming\AppNameTag;
use Deskhand\Core\Process\ProcessRunner;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Exception\DatabaseProvisionException;
use Deskhand\Exception\DeskhandException;
use Deskhand\Profile\StackProfile;

/**
 * The Laravel stack profile — the only place Laravel-specific knowledge lives,
 * keeping the generic core framework-free (§3.1). It maps the resolved worktree
 * record to Laravel `.env` keys and drives the artisan lifecycle through the
 * ProcessRunner seam.
 */
final class LaravelProfile implements StackProfile
{
    private const string DEFAULT_TEST_COMMAND = 'php artisan test --parallel';

    private const string TEST_COMMAND_WITHOUT_PARALLEL = 'php artisan test';

    /**
     * Writable directories a freshly-created worktree needs before artisan can
     * boot; created defensively since git only tracks their .gitignore markers.
     *
     * @var list<string>
     */
    private const array STORAGE_DIRS = [
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/testing',
        'storage/logs',
        'storage/app/public',
        'bootstrap/cache',
    ];

    public function __construct(
        private readonly ProcessRunner $process,
        private readonly Config $config,
        private readonly CapabilityDetector $capabilities,
    ) {}

    public function name(): string
    {
        return 'laravel';
    }

    public function envOverrides(WorktreeRecord $record, array $baseEnv, string $projectName): array
    {
        $overrides = [
            'DB_CONNECTION' => $record->db->engine,
            'DB_DATABASE' => $record->db->main,
            'APP_NAME' => AppNameTag::make($baseEnv['APP_NAME'] ?? null, $record->slug, $projectName),
            'APP_URL' => $record->url,
        ];

        if ($record->redis->isolated) {
            if ($record->redis->prefix !== null) {
                $overrides['REDIS_PREFIX'] = $record->redis->prefix;
            }

            if ($record->redis->dbIndex !== null) {
                $overrides['REDIS_DB'] = (string) $record->redis->dbIndex;
            }
        }

        return $overrides;
    }

    public function testingEnvOverrides(): array
    {
        return [
            'CACHE_STORE' => 'array',
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    public function generateAppKey(string $worktreePath): void
    {
        $result = $this->process->run(['php', 'artisan', 'key:generate', '--force'], $worktreePath);

        if ($result->failed()) {
            throw new DeskhandException('Unable to generate the app key: '.trim($result->stderr));
        }
    }

    public function provisionStorage(string $worktreePath): void
    {
        foreach (self::STORAGE_DIRS as $relative) {
            $dir = $worktreePath.'/'.$relative;

            if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
                throw new DeskhandException("Unable to create the storage directory {$dir}.");
            }
        }

        $result = $this->process->run(['php', 'artisan', 'storage:link'], $worktreePath);

        if ($result->failed()) {
            throw new DeskhandException('Unable to link storage: '.trim($result->stderr));
        }
    }

    public function migrate(string $worktreePath, string $databaseName, array $env = []): void
    {
        // Target the database by environment, leaving the configured command
        // verbatim (§4.1 step 9): Laravel's env() prefers the real environment
        // over the .env file, so DB_DATABASE here wins for this invocation.
        // DESKHAND_* facts ride alongside; DB_DATABASE takes precedence.
        $result = $this->process->runShell(
            $this->config->migrateCommand,
            $worktreePath,
            array_merge($env, ['DB_DATABASE' => $databaseName]),
        );

        if ($result->failed()) {
            throw new DatabaseProvisionException("Unable to migrate database {$databaseName}: ".trim($result->stderr));
        }
    }

    public function seed(string $worktreePath, array $env = []): void
    {
        $result = $this->process->runShell($this->config->seedCommand, $worktreePath, $env);

        if ($result->failed()) {
            throw new DatabaseProvisionException('Unable to seed the database: '.trim($result->stderr));
        }
    }

    public function verify(string $worktreePath, array $env = []): bool
    {
        $command = $this->config->testCommand;

        // Only the default command is downgraded; an explicitly configured
        // command runs verbatim (§4.1 step 12).
        if ($command === self::DEFAULT_TEST_COMMAND && ! $this->capabilities->hasParallelTesting($worktreePath)) {
            $command = self::TEST_COMMAND_WITHOUT_PARALLEL;
        }

        return $this->process->runShell($command, $worktreePath, $env)->successful();
    }
}
