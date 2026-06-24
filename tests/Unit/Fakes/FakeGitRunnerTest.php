<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeGitRunner;

it('tracks worktrees through add, list and remove', function () {
    $git = new FakeGitRunner;

    $git->addWorktree('/repo', '/repo/.claude/worktrees/feature-billing', 'feature/billing', createBranch: true);

    $worktrees = $git->listWorktrees('/repo');
    expect($worktrees)->toHaveCount(1)
        ->and($worktrees[0]->path)->toBe('/repo/.claude/worktrees/feature-billing')
        ->and($worktrees[0]->branch)->toBe('feature/billing');

    $git->removeWorktree('/repo', '/repo/.claude/worktrees/feature-billing');
    expect($git->listWorktrees('/repo'))->toBeEmpty();
});

it('reports branch existence from registered branches', function () {
    $git = new FakeGitRunner;
    $git->registerBranch('main');

    expect($git->branchExists('main', '/repo'))->toBeTrue()
        ->and($git->branchExists('feature/x', '/repo'))->toBeFalse();
});

it('treats a directory as a git repository by default', function () {
    $git = new FakeGitRunner;

    expect($git->isGitRepository('/repo'))->toBeTrue();

    $git->isRepository = false;
    expect($git->isGitRepository('/repo'))->toBeFalse();
});
