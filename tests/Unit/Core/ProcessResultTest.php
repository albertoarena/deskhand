<?php

declare(strict_types=1);

use Deskhand\Core\Process\ProcessResult;

it('reports success on a zero exit code', function () {
    $result = new ProcessResult(0, 'ok', '');

    expect($result->successful())->toBeTrue()
        ->and($result->failed())->toBeFalse()
        ->and($result->stdout)->toBe('ok')
        ->and($result->stderr)->toBe('');
});

it('reports failure on a non-zero exit code', function () {
    $result = new ProcessResult(1, '', 'nope');

    expect($result->successful())->toBeFalse()
        ->and($result->failed())->toBeTrue()
        ->and($result->exitCode)->toBe(1)
        ->and($result->stderr)->toBe('nope');
});
