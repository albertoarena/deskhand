<?php

declare(strict_types=1);

use Deskhand\Core\Git\SystemGitRunner;
use Deskhand\Core\Process\SystemProcessRunner;
use Deskhand\Exception\NotAGitRepositoryException;

function bootGitRepo(string $repo): void
{
    $runner = new SystemProcessRunner;
    $runner->run(['git', 'init', '-b', 'main'], $repo);
    $runner->run(['git', 'config', 'user.email', 'test@example.com'], $repo);
    $runner->run(['git', 'config', 'user.name', 'Test'], $repo);
    $runner->run(['git', 'config', 'commit.gpgsign', 'false'], $repo);
    $runner->run(['git', 'commit', '--allow-empty', '-m', 'init'], $repo);
}

beforeEach(function () {
    $this->root = deskhandTempDir();
    $this->repo = $this->root.'/repo';
    mkdir($this->repo, 0o775, true);
    bootGitRepo($this->repo);

    $this->git = new SystemGitRunner(new SystemProcessRunner);
});

afterEach(function () {
    deskhandRemoveDir($this->root);
});

it('detects a git repository', function () {
    expect($this->git->isGitRepository($this->repo))->toBeTrue();
});

it('reports a non-repository directory as not a git repository', function () {
    expect($this->git->isGitRepository($this->root))->toBeFalse()
        ->and($this->git->isGitRepository($this->root.'/does-not-exist'))->toBeFalse();
});

it('resolves the repository root from a subdirectory', function () {
    mkdir($this->repo.'/src/deep', 0o775, true);

    expect($this->git->repositoryRoot($this->repo.'/src/deep'))->toBe(realpath($this->repo));
});

it('throws when resolving the root of a non-repository', function () {
    $this->git->repositoryRoot($this->root);
})->throws(NotAGitRepositoryException::class);

it('detects existing and missing branches', function () {
    expect($this->git->branchExists('main', $this->repo))->toBeTrue()
        ->and($this->git->branchExists('nope', $this->repo))->toBeFalse();
});

it('adds a worktree on a new branch', function () {
    $path = $this->root.'/wt-feature';

    $this->git->addWorktree($this->repo, $path, 'feature-x', createBranch: true);

    expect(is_dir($path))->toBeTrue()
        ->and($this->git->branchExists('feature-x', $this->repo))->toBeTrue();

    $branches = array_map(fn ($w) => $w->branch, $this->git->listWorktrees($this->repo));
    expect($branches)->toContain('feature-x');
});

it('adds a worktree attached to an existing branch', function () {
    (new SystemProcessRunner)->run(['git', 'branch', 'existing'], $this->repo);
    $path = $this->root.'/wt-existing';

    $this->git->addWorktree($this->repo, $path, 'existing', createBranch: false);

    expect(is_dir($path))->toBeTrue();

    $branches = array_map(fn ($w) => $w->branch, $this->git->listWorktrees($this->repo));
    expect($branches)->toContain('existing');
});

it('lists the main worktree with its branch and head', function () {
    $worktrees = $this->git->listWorktrees($this->repo);

    expect($worktrees)->toHaveCount(1)
        ->and($worktrees[0]->path)->toBe(realpath($this->repo))
        ->and($worktrees[0]->branch)->toBe('main')
        ->and($worktrees[0]->head)->not->toBeNull();
});

it('removes a worktree', function () {
    $path = $this->root.'/wt-remove';
    $this->git->addWorktree($this->repo, $path, 'to-remove', createBranch: true);

    $this->git->removeWorktree($this->repo, $path);

    expect(is_dir($path))->toBeFalse()
        ->and($this->git->listWorktrees($this->repo))->toHaveCount(1);
});

it('prunes worktrees without error', function () {
    $path = $this->root.'/wt-prune';
    $this->git->addWorktree($this->repo, $path, 'prune-me', createBranch: true);
    deskhandRemoveDir($path); // remove the worktree directory behind git's back

    $this->git->pruneWorktrees($this->repo);

    expect($this->git->listWorktrees($this->repo))->toHaveCount(1);
});

it('removes a branch', function () {
    (new SystemProcessRunner)->run(['git', 'branch', 'temp'], $this->repo);
    expect($this->git->branchExists('temp', $this->repo))->toBeTrue();

    $this->git->removeBranch('temp', $this->repo);

    expect($this->git->branchExists('temp', $this->repo))->toBeFalse();
});
