<?php

declare(strict_types=1);

use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Process\ProcessResult;
use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Exception\DatabaseProvisionException;
use Deskhand\Exception\DeskhandException;
use Deskhand\Profile\Laravel\LaravelProfile;
use Deskhand\Tests\Fakes\FakeCapabilityDetector;
use Deskhand\Tests\Fakes\FakeProcessRunner;

function makeProfile(?FakeProcessRunner $process = null, ?FakeCapabilityDetector $caps = null, array $config = []): LaravelProfile
{
    return new LaravelProfile(
        $process ?? new FakeProcessRunner,
        ConfigLoader::fromArray($config),
        $caps ?? new FakeCapabilityDetector,
    );
}

function redisRecord(bool $isolated): WorktreeRecord
{
    return new WorktreeRecord(
        slug: 'feature-billing',
        branch: 'feature/billing',
        path: '.claude/worktrees/feature-billing',
        createdAt: '2026-06-24T10:00:00Z',
        db: new DatabaseRecord(engine: 'sqlite', shared: false, main: 'database/deskhand/feature-billing.sqlite'),
        ports: new PortAllocation(serve: 8312, vite: 5312),
        redis: $isolated
            ? new RedisAllocation(isolated: true, prefix: 'feature-billing:', dbIndex: 7)
            : new RedisAllocation(isolated: false),
        url: 'http://127.0.0.1:8312',
    );
}

it('identifies as laravel', function () {
    expect(makeProfile()->name())->toBe('laravel');
});

it('builds env overrides from the record and base APP_NAME', function () {
    $overrides = makeProfile()->envOverrides(sampleRecord(), ['APP_NAME' => 'Acme'], 'acme-project');

    expect($overrides['DB_CONNECTION'])->toBe('sqlite')
        ->and($overrides['DB_DATABASE'])->toBe('database/deskhand/feature-billing.sqlite')
        ->and($overrides['APP_NAME'])->toBe('Acme [feature-billing]')
        ->and($overrides['APP_URL'])->toBe('http://127.0.0.1:8312');
});

it('falls back to the project name when base APP_NAME is absent', function () {
    $overrides = makeProfile()->envOverrides(sampleRecord(), [], 'acme-project');

    expect($overrides['APP_NAME'])->toBe('acme-project [feature-billing]');
});

it('adds Redis keys only when isolation is active', function () {
    $isolated = makeProfile()->envOverrides(redisRecord(isolated: true), [], 'acme');
    $plain = makeProfile()->envOverrides(redisRecord(isolated: false), [], 'acme');

    expect($isolated['REDIS_PREFIX'])->toBe('feature-billing:')
        ->and($isolated['REDIS_DB'])->toBe('7')
        ->and($plain)->not->toHaveKey('REDIS_PREFIX')
        ->and($plain)->not->toHaveKey('REDIS_DB');
});

it('forces safe drivers in the testing env overrides', function () {
    expect(makeProfile()->testingEnvOverrides())->toBe([
        'CACHE_STORE' => 'array',
        'CACHE_DRIVER' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ]);
});

it('generates the app key in the worktree', function () {
    $process = new FakeProcessRunner;

    makeProfile($process)->generateAppKey('/wt');

    expect($process->calls[0]['command'])->toBe(['php', 'artisan', 'key:generate', '--force'])
        ->and($process->calls[0]['cwd'])->toBe('/wt');
});

it('fails clearly when key generation fails', function () {
    $process = new FakeProcessRunner;
    $process->queue(new ProcessResult(1, '', 'boom'));

    makeProfile($process)->generateAppKey('/wt');
})->throws(DeskhandException::class);

it('creates storage directories and links storage', function () {
    $dir = deskhandTempDir();
    $process = new FakeProcessRunner;

    makeProfile($process)->provisionStorage($dir);

    expect(is_dir($dir.'/storage/framework/cache/data'))->toBeTrue()
        ->and(is_dir($dir.'/storage/app/public'))->toBeTrue()
        ->and(is_dir($dir.'/bootstrap/cache'))->toBeTrue()
        ->and($process->calls[0]['command'])->toBe(['php', 'artisan', 'storage:link']);

    deskhandRemoveDir($dir);
});

it('migrates a named database by setting it in the environment', function () {
    $process = new FakeProcessRunner;

    makeProfile($process)->migrate('/wt', 'acme_wt_feature-billing');

    expect($process->shellCalls[0]['command'])->toBe('php artisan migrate')
        ->and($process->shellCalls[0]['cwd'])->toBe('/wt')
        ->and($process->shellCalls[0]['env'])->toBe(['DB_DATABASE' => 'acme_wt_feature-billing']);
});

it('fails clearly when migration fails', function () {
    $process = new FakeProcessRunner;
    $process->queue(new ProcessResult(1, '', 'migrate error'));

    makeProfile($process)->migrate('/wt', 'db');
})->throws(DatabaseProvisionException::class);

it('runs the configured seed command verbatim', function () {
    $process = new FakeProcessRunner;

    makeProfile($process, config: ['seed_command' => 'php artisan db:seed --class=DemoSeeder'])->seed('/wt');

    expect($process->shellCalls[0]['command'])->toBe('php artisan db:seed --class=DemoSeeder');
});

it('verifies green when the suite passes', function () {
    $process = new FakeProcessRunner;
    $process->queue(new ProcessResult(0));

    expect(makeProfile($process)->verify('/wt'))->toBeTrue()
        ->and($process->shellCalls[0]['command'])->toBe('php artisan test --parallel');
});

it('reports red when the suite fails', function () {
    $process = new FakeProcessRunner;
    $process->queue(new ProcessResult(1));

    expect(makeProfile($process)->verify('/wt'))->toBeFalse();
});

it('drops --parallel when paratest is unavailable and the command is the default', function () {
    $process = new FakeProcessRunner;
    $caps = new FakeCapabilityDetector;
    $caps->parallelTesting = false;

    makeProfile($process, $caps)->verify('/wt');

    expect($process->shellCalls[0]['command'])->toBe('php artisan test');
});

it('keeps a custom test command verbatim even without paratest', function () {
    $process = new FakeProcessRunner;
    $caps = new FakeCapabilityDetector;
    $caps->parallelTesting = false;

    makeProfile($process, $caps, ['test_command' => 'vendor/bin/pest'])->verify('/wt');

    expect($process->shellCalls[0]['command'])->toBe('vendor/bin/pest');
});
