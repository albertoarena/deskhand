<?php

declare(strict_types=1);

use Deskhand\Core\Process\SystemProcessRunner;

beforeEach(function () {
    $this->runner = new SystemProcessRunner;
    $this->dir = deskhandTempDir();
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('runs a command and captures stdout', function () {
    $result = $this->runner->run(['printf', 'hello'], $this->dir);

    expect($result->successful())->toBeTrue()
        ->and($result->exitCode)->toBe(0)
        ->and($result->stdout)->toBe('hello');
});

it('captures stderr and a non-zero exit code', function () {
    $result = $this->runner->run(['sh', '-c', 'printf oops >&2; exit 3'], $this->dir);

    expect($result->failed())->toBeTrue()
        ->and($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('oops');
});

it('runs in the given working directory', function () {
    file_put_contents($this->dir.'/marker.txt', 'in-cwd');

    $result = $this->runner->run(['cat', 'marker.txt'], $this->dir);

    expect($result->stdout)->toBe('in-cwd');
});

it('merges extra environment variables', function () {
    $result = $this->runner->run(['sh', '-c', 'printf %s "$DESKHAND_X"'], $this->dir, ['DESKHAND_X' => 'merged']);

    expect($result->stdout)->toBe('merged');
});

it('reports a missing binary as a failed result without throwing', function () {
    $result = $this->runner->run(['deskhand-nonexistent-binary-zzz'], $this->dir);

    expect($result->failed())->toBeTrue()
        ->and($result->exitCode)->not->toBe(0);
});

it('enforces a timeout', function () {
    $result = $this->runner->run(['sleep', '5'], $this->dir, [], 0.2);

    expect($result->failed())->toBeTrue();
});
