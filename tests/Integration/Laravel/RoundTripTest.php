<?php

declare(strict_types=1);

use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Core\Registry\JsonRegistry;
use Deskhand\Down\DefaultDownRunnerFactory;
use Deskhand\Up\DefaultUpRunnerFactory;
use Deskhand\Up\UpRequest;

/**
 * End-to-end round-trip against a freshly scaffolded Laravel app: real composer,
 * artisan, git and SQLite. Gated on DESKHAND_TEST_LARAVEL (it needs network +
 * composer and takes minutes), so CI and contributors without it stay green.
 */
beforeEach(function () {
    if (getenv('DESKHAND_TEST_LARAVEL') === false) {
        $this->markTestSkipped('Set DESKHAND_TEST_LARAVEL=1 to run the real Laravel round-trip (needs composer + network).');
    }

    $this->dir = deskhandTempDir();
    $this->app = $this->dir.'/app';
    $this->process = new SystemProcessRunner;

    $this->process->run(['composer', 'create-project', 'laravel/laravel', 'app', '--quiet'], $this->dir, timeout: 600.0);
    $this->process->run(['git', 'init', '-b', 'main'], $this->app);
    $this->process->run(['git', 'config', 'user.email', 'it@example.com'], $this->app);
    $this->process->run(['git', 'config', 'user.name', 'IT'], $this->app);
    $this->process->run(['git', 'config', 'commit.gpgsign', 'false'], $this->app);
    $this->process->run(['git', 'add', '-A'], $this->app);
    $this->process->run(['git', 'commit', '-m', 'init laravel'], $this->app);
});

afterEach(function () {
    if (isset($this->dir)) {
        deskhandRemoveDir($this->dir);
    }
});

it('provisions a verified isolated worktree and tears it down cleanly', function () {
    $runner = (new DefaultUpRunnerFactory)->create($this->app);

    $result = $runner->run(new UpRequest(
        branch: 'feature/it',
        workingDirectory: $this->app,
        createdAt: gmdate('Y-m-d\TH:i:s\Z'),
    ));

    $record = $result->record;
    $worktree = $this->app.'/'.$record->path;

    // Provisioned and verified against the real suite.
    expect($result->verified)->toBeTrue()
        ->and(is_dir($worktree))->toBeTrue()
        ->and(is_file($worktree.'/.env'))->toBeTrue()
        ->and(is_file($worktree.'/'.$record->db->main))->toBeTrue();

    // Isolated: the worktree env points at the deskhand DB, the base does not.
    expect(file_get_contents($worktree.'/.env'))->toContain('DB_DATABASE=database/deskhand/feature-it.sqlite')
        ->and(is_dir($this->app.'/database/deskhand'))->toBeFalse();

    // Recorded in the registry.
    $registry = new JsonRegistry(JsonRegistry::pathFor($this->app));
    expect($registry->find('feature-it')?->branch)->toBe('feature/it');

    // Tear down: only deskhand's artifacts are removed.
    (new DefaultDownRunnerFactory)->create($this->app)->tearDown($record, keepBranch: false);

    expect(is_file($worktree.'/'.$record->db->main))->toBeFalse()
        ->and(is_dir($worktree))->toBeFalse();

    $branches = $this->process->run(['git', 'branch', '--list', 'feature/it'], $this->app);
    expect(trim($branches->stdout))->toBe('');
});
