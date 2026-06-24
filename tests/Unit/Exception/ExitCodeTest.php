<?php

declare(strict_types=1);

use Deskhand\Exception\DatabaseProvisionException;
use Deskhand\Exception\DeskhandException;
use Deskhand\Exception\MissingCapabilityException;
use Deskhand\Exception\NotAGitRepositoryException;
use Deskhand\Exception\RegistryException;
use Deskhand\Exception\VerificationFailedException;
use Deskhand\Exception\WorktreeExistsException;

it('maps each exception to its specified exit code', function (string $class, int $expected) {
    /** @var DeskhandException $exception */
    $exception = new $class('boom');

    expect($exception)->toBeInstanceOf(DeskhandException::class)
        ->and($exception->exitCode())->toBe($expected);
})->with([
    'generic' => [DeskhandException::class, 1],
    'not a git repo' => [NotAGitRepositoryException::class, 2],
    'missing capability' => [MissingCapabilityException::class, 3],
    'worktree exists' => [WorktreeExistsException::class, 4],
    'database provision' => [DatabaseProvisionException::class, 5],
    'verification failed' => [VerificationFailedException::class, 6],
    'registry' => [RegistryException::class, 7],
]);

it('keeps the message it was constructed with', function () {
    expect((new NotAGitRepositoryException('not here'))->getMessage())->toBe('not here');
});
