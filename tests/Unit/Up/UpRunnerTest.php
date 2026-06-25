<?php

declare(strict_types=1);

use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Gitignore\GitignoreManager;
use Deskhand\Core\Planning\WorktreePlanner;
use Deskhand\Core\Process\ProcessResult;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Exception\DatabaseProvisionException;
use Deskhand\Exception\DeskhandException;
use Deskhand\Exception\MissingCapabilityException;
use Deskhand\Exception\NotAGitRepositoryException;
use Deskhand\Exception\VerificationFailedException;
use Deskhand\Tests\Fakes\FakeCapabilityDetector;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakeGitRunner;
use Deskhand\Tests\Fakes\FakeProcessRunner;
use Deskhand\Tests\Fakes\FakeRegistry;
use Deskhand\Tests\Fakes\FakeStackProfile;
use Deskhand\Up\UpRequest;
use Deskhand\Up\UpRunner;

function makeUpRunner(object $t, array $config = []): UpRunner
{
    $cfg = ConfigLoader::fromArray($config);

    return new UpRunner(
        $t->git,
        $t->process,
        $t->registry,
        $t->env,
        $t->caps,
        $t->profile,
        $t->dbFactory,
        new WorktreePlanner($cfg, new UrlResolver($cfg), $t->registry),
        new GitignoreManager,
        $cfg,
    );
}

function upRequest(object $t, array $o = []): UpRequest
{
    return new UpRequest(
        branch: $o['branch'] ?? 'feature/billing',
        workingDirectory: $t->repo,
        createdAt: '2026-06-25T10:00:00Z',
        engine: $o['engine'] ?? 'sqlite',
        shared: $o['shared'] ?? false,
        pathFlag: $o['pathFlag'] ?? null,
        urlFlag: $o['urlFlag'] ?? null,
        skipEnvaudit: $o['skipEnvaudit'] ?? false,
        skipRedisIsolation: $o['skipRedisIsolation'] ?? false,
        skipVerify: $o['skipVerify'] ?? false,
    );
}

function ranCommands(FakeProcessRunner $process): array
{
    return array_map(fn (array $c): array => $c['command'], $process->calls);
}

beforeEach(function () {
    $this->repo = deskhandTempDir();
    $this->git = new FakeGitRunner;
    $this->process = new FakeProcessRunner;
    $this->registry = new FakeRegistry;
    $this->env = new FakeEnvMaterializer;
    $this->env->seed($this->repo.'/.env', ['APP_NAME' => 'Acme', 'DB_DATABASE' => 'acme', 'APP_URL' => 'http://acme.test']);
    $this->caps = new FakeCapabilityDetector;
    $this->profile = new FakeStackProfile;
    $this->dbFactory = new FakeDatabaseProvisionerFactory(new FakeDatabaseProvisioner('sqlite'));
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('provisions a SQLite worktree end to end', function () {
    $result = makeUpRunner($this)->run(upRequest($this));

    expect($result->record->slug)->toBe('feature-billing')
        ->and($this->registry->find('feature-billing'))->not->toBeNull()
        ->and($this->profile->appKeyGenerated)->toBeTrue()
        ->and($this->profile->storageProvisioned)->toBeTrue()
        ->and($this->profile->migrated)->toBe(['database/deskhand/feature-billing.sqlite'])
        ->and($this->dbFactory->provisioner->created)->toContain('database/deskhand/feature-billing.sqlite')
        ->and(ranCommands($this->process))->toContain(['composer', 'install', '--no-interaction'])
        ->and($result->verified)->toBeTrue()
        ->and($result->envauditSkipped)->toBeTrue();
});

it('materializes .env and .env.testing with the profile overrides', function () {
    $this->profile->overrides = ['DB_DATABASE' => 'database/deskhand/feature-billing.sqlite'];
    $this->profile->testingOverrides = ['CACHE_STORE' => 'array'];

    makeUpRunner($this)->run(upRequest($this));

    $wt = $this->repo.'/.claude/worktrees/feature-billing';

    expect($this->env->written)->toContain($wt.'/.env')->toContain($wt.'/.env.testing')
        ->and($this->env->read($wt.'/.env')['DB_DATABASE'])->toBe('database/deskhand/feature-billing.sqlite')
        ->and($this->env->read($wt.'/.env')['APP_NAME'])->toBe('Acme')
        ->and($this->env->read($wt.'/.env.testing')['CACHE_STORE'])->toBe('array')
        ->and($this->env->read($wt.'/.env.testing')['DB_DATABASE'])->toBe('database/deskhand/feature-billing.sqlite');
});

it('ensures the gitignore managed block', function () {
    $result = makeUpRunner($this)->run(upRequest($this));

    expect($result->gitignoreAdded)->toBe(['.claude/worktrees/', '.claude/deskhand/', 'database/deskhand/'])
        ->and((string) file_get_contents($this->repo.'/.gitignore'))->toContain('# deskhand (managed)');
});

it('fails when not inside a git repository', function () {
    $this->git->isRepository = false;

    makeUpRunner($this)->run(upRequest($this));
})->throws(NotAGitRepositoryException::class);

it('fails when composer is missing', function () {
    $this->caps->composer = false;

    makeUpRunner($this)->run(upRequest($this));
})->throws(MissingCapabilityException::class);

it('persists the record even when a later step fails', function () {
    $this->profile->verifyResult = false;

    try {
        makeUpRunner($this)->run(upRequest($this));
    } catch (VerificationFailedException) {
        // expected
    }

    expect($this->registry->find('feature-billing'))->not->toBeNull();
});

it('fails verification with exit code 6 when the suite is red', function () {
    $this->profile->verifyResult = false;

    try {
        makeUpRunner($this)->run(upRequest($this));
        $this->fail('expected VerificationFailedException');
    } catch (VerificationFailedException $e) {
        expect($e->exitCode())->toBe(6);
    }
});

it('skips verification with --no-verify', function () {
    $this->profile->verifyResult = false; // would fail if it ran

    $result = makeUpRunner($this)->run(upRequest($this, ['skipVerify' => true]));

    expect($result->verifySkipped)->toBeTrue()
        ->and($result->verified)->toBeFalse();
});

it('skips database, migrate and seed under --shared-db', function () {
    $result = makeUpRunner($this, ['seed' => true])->run(upRequest($this, ['shared' => true]));

    expect($result->sharedDb)->toBeTrue()
        ->and($this->dbFactory->calls)->toBe([])
        ->and($this->profile->migrated)->toBe([])
        ->and($this->profile->seeded)->toBeFalse()
        ->and($result->record->db->shared)->toBeTrue();
});

it('seeds when configured', function () {
    makeUpRunner($this, ['seed' => true])->run(upRequest($this));

    expect($this->profile->seeded)->toBeTrue();
});

it('creates and migrates the main plus numbered test databases for MySQL', function () {
    $this->dbFactory = new FakeDatabaseProvisionerFactory(new FakeDatabaseProvisioner('mysql'));

    makeUpRunner($this)->run(upRequest($this, ['engine' => 'mysql']));

    // CPU count falls back to 4 with the fake process runner -> 4 test DBs.
    expect($this->dbFactory->provisioner->created)->toContain('acme_wt_feature-billing')
        ->and($this->dbFactory->provisioner->created)->toContain('acme_wt_feature-billing_test_1')
        ->and($this->dbFactory->provisioner->created)->toHaveCount(5)
        ->and($this->profile->migrated)->toHaveCount(5);
});

it('fails when MySQL is unreachable', function () {
    $provisioner = new FakeDatabaseProvisioner('mysql');
    $provisioner->connectable = false;
    $this->dbFactory = new FakeDatabaseProvisionerFactory($provisioner);

    makeUpRunner($this)->run(upRequest($this, ['engine' => 'mysql']));
})->throws(DatabaseProvisionException::class);

it('is idempotent: a re-run reuses the worktree without duplicating it', function () {
    makeUpRunner($this)->run(upRequest($this));
    makeUpRunner($this)->run(upRequest($this));

    expect($this->git->listWorktrees($this->repo))->toHaveCount(1)
        ->and($this->registry->all())->toHaveCount(1);
});

it('passes the DESKHAND_* facts to migrate and verify', function () {
    makeUpRunner($this)->run(upRequest($this));

    expect($this->profile->migrateEnv['DESKHAND_SLUG'])->toBe('feature-billing')
        ->and($this->profile->migrateEnv['DESKHAND_DB_NAME'])->toBe('database/deskhand/feature-billing.sqlite')
        ->and($this->profile->verifyEnv['DESKHAND_BRANCH'])->toBe('feature/billing')
        ->and($this->profile->verifyEnv['DESKHAND_SERVE_PORT'])->not->toBe('');
});

it('runs post-up hooks verbatim with the DESKHAND_* facts', function () {
    makeUpRunner($this, ['post_up_hooks' => ['php artisan cache:clear']])->run(upRequest($this));

    expect($this->process->shellCalls[0]['command'])->toBe('php artisan cache:clear')
        ->and($this->process->shellCalls[0]['env']['DESKHAND_SLUG'])->toBe('feature-billing')
        ->and($this->process->shellCalls[0]['env']['DESKHAND_DB_NAME'])->toBe('database/deskhand/feature-billing.sqlite');
});

it('fails when a post-up hook fails', function () {
    $this->process->queue(new ProcessResult(0));          // composer install
    $this->process->queue(new ProcessResult(1, '', 'hook boom')); // the hook

    makeUpRunner($this, ['post_up_hooks' => ['false']])->run(upRequest($this));
})->throws(DeskhandException::class);

it('installs frontend dependencies when a package.json is present', function () {
    $this->caps->frontend = true;
    $this->caps->packageManager = 'npm';

    $result = makeUpRunner($this)->run(upRequest($this));

    expect($result->packageManager)->toBe('npm')
        ->and(ranCommands($this->process))->toContain(['npm', 'install', '--prefer-offline']);
});
