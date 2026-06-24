<?php

declare(strict_types=1);

use Deskhand\Core\Process\ProcessResult;
use Deskhand\Tests\Fakes\FakeProcessRunner;

it('returns queued results in order and records each call', function () {
    $runner = new FakeProcessRunner;
    $runner->queue(new ProcessResult(0, 'first', ''));
    $runner->queue(new ProcessResult(1, '', 'second'));

    $a = $runner->run(['composer', 'install'], '/work', ['APP_ENV' => 'local']);
    $b = $runner->run(['php', 'artisan', 'migrate'], '/work');

    expect($a->stdout)->toBe('first')
        ->and($b->exitCode)->toBe(1)
        ->and($runner->calls)->toHaveCount(2)
        ->and($runner->calls[0]['command'])->toBe(['composer', 'install'])
        ->and($runner->calls[0]['cwd'])->toBe('/work')
        ->and($runner->calls[0]['env'])->toBe(['APP_ENV' => 'local']);
});

it('defaults to a success result when nothing is queued', function () {
    $runner = new FakeProcessRunner;

    expect($runner->run(['true'], '/tmp')->successful())->toBeTrue();
});
