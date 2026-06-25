<?php

declare(strict_types=1);

use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Down\DownRunner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakeGitRunner;
use Deskhand\Tests\Fakes\FakeRegistry;

function mysqlWorktreeRecord(): WorktreeRecord
{
    return new WorktreeRecord(
        slug: 'feature-payments',
        branch: 'feature/payments',
        path: '.claude/worktrees/feature-payments',
        createdAt: '2026-06-24T11:00:00Z',
        db: new DatabaseRecord(
            engine: 'mysql',
            shared: false,
            main: 'acme_wt_feature-payments',
            testDbs: ['acme_wt_feature-payments_test_1', 'acme_wt_feature-payments_test_2'],
        ),
        ports: new PortAllocation(serve: 8350, vite: 5350),
        redis: new RedisAllocation(isolated: false),
        url: 'http://127.0.0.1:8350',
    );
}

function sharedWorktreeRecord(): WorktreeRecord
{
    return new WorktreeRecord(
        slug: 'readonly-look',
        branch: 'readonly/look',
        path: '.claude/worktrees/readonly-look',
        createdAt: '2026-06-24T12:00:00Z',
        db: new DatabaseRecord(engine: 'mysql', shared: true, main: 'acme', testDbs: []),
        ports: new PortAllocation(serve: 8360, vite: 5360),
        redis: new RedisAllocation(isolated: false),
        url: 'http://127.0.0.1:8360',
    );
}

function downRunner(object $t, FakeDatabaseProvisioner $provisioner): DownRunner
{
    return new DownRunner(
        $t->git,
        $t->registry,
        new FakeDatabaseProvisionerFactory($provisioner),
        new FakeEnvMaterializer,
        $t->repo,
    );
}

beforeEach(function () {
    $this->repo = deskhandTempDir();
    $this->git = new FakeGitRunner;
    $this->git->root = $this->repo;
    $this->registry = new FakeRegistry;
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('finds a record by slug or branch', function () {
    $this->registry->save(sampleRecord());
    $runner = downRunner($this, new FakeDatabaseProvisioner('sqlite'));

    expect($runner->find('feature/billing')?->slug)->toBe('feature-billing')
        ->and($runner->find('feature-billing'))->not->toBeNull()
        ->and($runner->find('missing'))->toBeNull();
});

it('drops only the registered databases, removes the worktree, branch and registry entry', function () {
    $record = sampleRecord();
    $this->registry->save($record);
    $this->git->registerBranch('feature/billing');
    $this->git->addWorktree($this->repo, $this->repo.'/.claude/worktrees/feature-billing', 'feature/billing', false);

    $provisioner = new FakeDatabaseProvisioner('sqlite');
    $result = downRunner($this, $provisioner)->tearDown($record, keepBranch: false);

    expect($provisioner->dropped)->toBe(['database/deskhand/feature-billing.sqlite'])
        ->and($this->registry->all())->toBe([])
        ->and($this->git->listWorktrees($this->repo))->toBe([])
        ->and($this->git->branchExists('feature/billing', $this->repo))->toBeFalse()
        ->and($result->branchRemoved)->toBeTrue()
        ->and($result->warnings)->toBe([]);
});

it('drops the main and every numbered test database for MySQL', function () {
    $record = mysqlWorktreeRecord();
    $this->registry->save($record);

    $provisioner = new FakeDatabaseProvisioner('mysql');
    downRunner($this, $provisioner)->tearDown($record, keepBranch: false);

    expect($provisioner->dropped)->toBe([
        'acme_wt_feature-payments',
        'acme_wt_feature-payments_test_1',
        'acme_wt_feature-payments_test_2',
    ]);
});

it('never drops a shared database', function () {
    $record = sharedWorktreeRecord();
    $this->registry->save($record);

    $provisioner = new FakeDatabaseProvisioner('mysql');
    $result = downRunner($this, $provisioner)->tearDown($record, keepBranch: false);

    expect($provisioner->dropped)->toBe([])
        ->and($this->registry->all())->toBe([]);
});

it('keeps the branch with --keep-branch', function () {
    $record = sampleRecord();
    $this->registry->save($record);
    $this->git->registerBranch('feature/billing');

    $result = downRunner($this, new FakeDatabaseProvisioner('sqlite'))->tearDown($record, keepBranch: true);

    expect($this->git->branchExists('feature/billing', $this->repo))->toBeTrue()
        ->and($result->branchRemoved)->toBeFalse();
});

it('removes the storage symlink as a link', function () {
    $record = sampleRecord();
    $this->registry->save($record);
    $worktree = $this->repo.'/.claude/worktrees/feature-billing';
    mkdir($worktree.'/public', 0o775, true);
    mkdir($this->repo.'/real-storage', 0o775, true);
    symlink($this->repo.'/real-storage', $worktree.'/public/storage');

    downRunner($this, new FakeDatabaseProvisioner('sqlite'))->tearDown($record, keepBranch: false);

    expect(is_link($worktree.'/public/storage'))->toBeFalse()
        ->and(is_dir($this->repo.'/real-storage'))->toBeTrue(); // never followed into the target
});

it('is best-effort: a failing step is recorded but does not abort the rest', function () {
    $record = sampleRecord();
    $this->registry->save($record);
    $this->git->failRemoveWorktree = true;

    $provisioner = new FakeDatabaseProvisioner('sqlite');
    $result = downRunner($this, $provisioner)->tearDown($record, keepBranch: false);

    expect($result->warnings)->not->toBe([])
        ->and($provisioner->dropped)->toBe(['database/deskhand/feature-billing.sqlite'])
        ->and($this->registry->all())->toBe([]); // registry still cleaned up
});
