<?php

declare(strict_types=1);

use Deskhand\Core\Gitignore\GitignoreManager;

beforeEach(function () {
    $this->dir = deskhandTempDir();
    $this->path = $this->dir.'/.gitignore';
    $this->manager = new GitignoreManager;
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('creates the managed block when no .gitignore exists', function () {
    $added = $this->manager->ensure($this->dir);

    expect($added)->toBe(['.claude/worktrees/', '.claude/deskhand/', 'database/deskhand/']);

    $contents = (string) file_get_contents($this->path);
    expect($contents)->toContain('# deskhand (managed)')
        ->and($contents)->toContain('.claude/worktrees/')
        ->and($contents)->toContain('.claude/deskhand/')
        ->and($contents)->toContain('database/deskhand/');
});

it('is idempotent — a second run adds nothing and leaves the file unchanged', function () {
    $this->manager->ensure($this->dir);
    $first = (string) file_get_contents($this->path);

    $added = $this->manager->ensure($this->dir);

    expect($added)->toBe([])
        ->and((string) file_get_contents($this->path))->toBe($first);
});

it('preserves existing unrelated entries and never reorders them', function () {
    file_put_contents($this->path, "/vendor/\n/node_modules/\n");

    $this->manager->ensure($this->dir);

    $contents = (string) file_get_contents($this->path);
    expect($contents)->toStartWith("/vendor/\n/node_modules/\n")
        ->and($contents)->toContain('# deskhand (managed)');
});

it('adds only the missing managed lines without duplicating the marker', function () {
    file_put_contents($this->path, "# deskhand (managed)\n.claude/worktrees/\ndatabase/deskhand/\n");

    $added = $this->manager->ensure($this->dir);

    expect($added)->toBe(['.claude/deskhand/']);

    $contents = (string) file_get_contents($this->path);
    expect(substr_count($contents, '# deskhand (managed)'))->toBe(1)
        ->and(substr_count($contents, '.claude/deskhand/'))->toBe(1);
});

it('adds nothing when all managed paths are already present', function () {
    file_put_contents($this->path, ".claude/worktrees/\n.claude/deskhand/\ndatabase/deskhand/\n");

    expect($this->manager->ensure($this->dir))->toBe([]);
});

it('does not duplicate entries across repeated runs on a populated file', function () {
    file_put_contents($this->path, "/vendor/\n");
    $this->manager->ensure($this->dir);
    $this->manager->ensure($this->dir);

    $contents = (string) file_get_contents($this->path);
    expect(substr_count($contents, '.claude/worktrees/'))->toBe(1)
        ->and(substr_count($contents, '# deskhand (managed)'))->toBe(1);
});
