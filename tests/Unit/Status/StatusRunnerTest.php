<?php

declare(strict_types=1);

use Deskhand\Status\StatusRunner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakePortChecker;
use Deskhand\Tests\Fakes\FakeRegistry;

function statusRunner(object $t): StatusRunner
{
    return new StatusRunner(
        $t->registry,
        new FakeDatabaseProvisionerFactory($t->provisioner),
        new FakeEnvMaterializer,
        $t->ports,
        $t->repo,
    );
}

function makeHealthyWorktree(object $t): void
{
    $t->registry->save(sampleRecord());
    $worktree = $t->repo.'/.claude/worktrees/feature-billing';
    mkdir($worktree, 0o775, true);
    file_put_contents($worktree.'/.env', 'APP_NAME=Worktree');
    $t->provisioner->markExisting('database/deskhand/feature-billing.sqlite');
}

beforeEach(function () {
    $this->repo = deskhandTempDir();
    $this->registry = new FakeRegistry;
    $this->provisioner = new FakeDatabaseProvisioner('sqlite');
    $this->ports = new FakePortChecker;
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('reports a healthy worktree', function () {
    makeHealthyWorktree($this);

    $health = statusRunner($this)->one('feature-billing');

    expect($health)->not->toBeNull()
        ->and($health->worktreeExists)->toBeTrue()
        ->and($health->envExists)->toBeTrue()
        ->and($health->databaseReachable)->toBeTrue()
        ->and($health->healthy())->toBeTrue()
        ->and($health->problems)->toBe([]);
});

it('flags a missing worktree directory', function () {
    $this->registry->save(sampleRecord());

    $health = statusRunner($this)->one('feature-billing');

    expect($health->worktreeExists)->toBeFalse()
        ->and($health->healthy())->toBeFalse()
        ->and($health->problems)->toContain('worktree directory missing');
});

it('flags a missing env and an unreachable database', function () {
    $this->registry->save(sampleRecord());
    mkdir($this->repo.'/.claude/worktrees/feature-billing', 0o775, true); // dir exists, no .env, db not marked

    $health = statusRunner($this)->one('feature-billing');

    expect($health->envExists)->toBeFalse()
        ->and($health->databaseReachable)->toBeFalse()
        ->and($health->problems)->toContain('.env missing')
        ->and($health->problems)->toContain('database unreachable or missing');
});

it('reports ports in use', function () {
    makeHealthyWorktree($this);
    $this->ports->inUse = [8312]; // sampleRecord serve port

    $health = statusRunner($this)->one('feature-billing');

    expect($health->servePortInUse)->toBeTrue()
        ->and($health->vitePortInUse)->toBeFalse()
        ->and($health->healthy())->toBeTrue(); // port-in-use is informational, not a problem
});

it('returns health for every record', function () {
    makeHealthyWorktree($this);

    expect(statusRunner($this)->all())->toHaveCount(1);
});

it('returns null for an unknown target', function () {
    expect(statusRunner($this)->one('nope'))->toBeNull();
});
