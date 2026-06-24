<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeCapabilityDetector;

it('defaults to a fully-capable environment', function () {
    $caps = new FakeCapabilityDetector;

    expect($caps->hasComposer())->toBeTrue()
        ->and($caps->hasMysqlClient())->toBeTrue();
});

it('exposes configurable capability flags', function () {
    $caps = new FakeCapabilityDetector;
    $caps->composer = false;
    $caps->mysqlClient = false;

    expect($caps->hasComposer())->toBeFalse()
        ->and($caps->hasMysqlClient())->toBeFalse();
});

it('detects the package manager from a configurable lockfile signal', function () {
    $caps = new FakeCapabilityDetector;
    $caps->packageManager = 'yarn';

    expect($caps->detectPackageManager('/project'))->toBe('yarn');
});

it('reports frontend and parallel-testing availability', function () {
    $caps = new FakeCapabilityDetector;
    $caps->frontend = true;
    $caps->parallelTesting = false;

    expect($caps->hasFrontend('/project'))->toBeTrue()
        ->and($caps->hasParallelTesting('/project'))->toBeFalse();
});
