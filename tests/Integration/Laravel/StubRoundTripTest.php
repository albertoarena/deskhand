<?php

declare(strict_types=1);

use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Core\Registry\JsonRegistry;
use Deskhand\Down\DefaultDownRunnerFactory;
use Deskhand\Up\DefaultUpRunnerFactory;
use Deskhand\Up\UpRequest;

/**
 * Default integration round-trip (implementation.md §11): exercises the real
 * git / process / SQLite / filesystem wiring end-to-end against a hermetic,
 * framework-free fixture (tests/Fixtures/laravel-app, with a stub `artisan`).
 *
 * Unlike the gated real-Laravel {@see RoundTripTest}, this needs no network and
 * no framework, so `up` → verify → `down` is covered on every CI cell. It still
 * shells out to git and composer, so it skips cleanly where those are absent.
 */
beforeEach(function () {
    $process = new SystemProcessRunner;

    foreach (['git', 'composer'] as $binary) {
        if ($process->run(['which', $binary], sys_get_temp_dir())->failed()) {
            $this->markTestSkipped("{$binary} is required for the round-trip integration test.");
        }
    }

    $this->process = $process;
    $this->dir = deskhandTempDir();
    $this->app = $this->dir.'/app';

    // Stage the committed fixture into a throwaway working tree and make it a
    // repo. The base `.env` is created from the placeholder example, mirroring
    // the real `cp .env.example .env` bootstrap.
    deskhandCopyDir(dirname(__DIR__, 2).'/Fixtures/laravel-app', $this->app);
    copy($this->app.'/.env.example', $this->app.'/.env');

    $this->process->run(['git', 'init', '-b', 'main'], $this->app);
    $this->process->run(['git', 'config', 'user.email', 'it@example.com'], $this->app);
    $this->process->run(['git', 'config', 'user.name', 'IT'], $this->app);
    $this->process->run(['git', 'config', 'commit.gpgsign', 'false'], $this->app);
    $this->process->run(['git', 'add', '-A'], $this->app);
    $this->process->run(['git', 'commit', '-m', 'init fixture'], $this->app);
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

    // Provisioned and verified against the stub suite (which reads the real DB).
    expect($result->verified)->toBeTrue()
        ->and(is_dir($worktree))->toBeTrue()
        ->and(is_file($worktree.'/.env'))->toBeTrue()
        ->and(is_file($worktree.'/'.$record->db->main))->toBeTrue();

    // Isolated: the worktree env points at the deskhand DB; the base project
    // never gains a deskhand database directory.
    expect(file_get_contents($worktree.'/.env'))->toContain('DB_DATABASE=database/deskhand/feature-it.sqlite')
        ->and(is_dir($this->app.'/database/deskhand'))->toBeFalse();

    // The worktree received its own freshly generated APP_KEY (never the base's).
    expect(file_get_contents($worktree.'/.env'))->toMatch('/^APP_KEY=base64:.+$/m');

    // Recorded in the registry under the derived slug.
    $registry = new JsonRegistry(JsonRegistry::pathFor($this->app));
    expect($registry->find('feature-it')?->branch)->toBe('feature/it');

    // Tear down: only deskhand's artifacts are removed.
    (new DefaultDownRunnerFactory)->create($this->app)->tearDown($record, keepBranch: false);

    expect(is_file($worktree.'/'.$record->db->main))->toBeFalse()
        ->and(is_dir($worktree))->toBeFalse()
        ->and((new JsonRegistry(JsonRegistry::pathFor($this->app)))->find('feature-it'))->toBeNull();

    $branches = $this->process->run(['git', 'branch', '--list', 'feature/it'], $this->app);
    expect(trim($branches->stdout))->toBe('');
});
