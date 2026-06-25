<?php

declare(strict_types=1);

use Deskhand\Core\Capability\SystemCapabilityDetector;
use Deskhand\Core\Process\ProcessResult;
use Deskhand\Tests\Fakes\FakeProcessRunner;

beforeEach(function () {
    $this->dir = deskhandTempDir();
    $this->process = new FakeProcessRunner;
    $this->detector = new SystemCapabilityDetector($this->process, $this->dir);
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('detects a host binary as present when which succeeds', function (string $method, string $binary) {
    $this->process->queue(new ProcessResult(0));

    expect($this->detector->{$method}())->toBeTrue()
        ->and($this->process->calls[0]['command'])->toBe(['which', $binary]);
})->with([
    'composer' => ['hasComposer', 'composer'],
    'npm' => ['hasNpm', 'npm'],
    'yarn' => ['hasYarn', 'yarn'],
    'mysql client' => ['hasMysqlClient', 'mysql'],
]);

it('detects a host binary as absent when which fails', function (string $method) {
    $this->process->queue(new ProcessResult(1));

    expect($this->detector->{$method}())->toBeFalse();
})->with([
    ['hasComposer'],
    ['hasNpm'],
    ['hasYarn'],
    ['hasMysqlClient'],
]);

it('detects a frontend by the presence of package.json', function () {
    expect($this->detector->hasFrontend($this->dir))->toBeFalse();

    file_put_contents($this->dir.'/package.json', '{}');

    expect($this->detector->hasFrontend($this->dir))->toBeTrue();
});

it('resolves the package manager from the lockfile', function (array $lockfiles, ?string $expected) {
    foreach ($lockfiles as $file) {
        file_put_contents($this->dir.'/'.$file, '');
    }

    expect($this->detector->detectPackageManager($this->dir))->toBe($expected);
})->with([
    'yarn only' => [['yarn.lock'], 'yarn'],
    'npm only' => [['package-lock.json'], 'npm'],
    'both is ambiguous' => [['yarn.lock', 'package-lock.json'], null],
    'neither is ambiguous' => [[], null],
]);

it('detects parallel testing when paratest is a declared dev dependency', function () {
    file_put_contents($this->dir.'/composer.json', json_encode([
        'require-dev' => ['brianium/paratest' => '^7.0'],
    ]));

    expect($this->detector->hasParallelTesting($this->dir))->toBeTrue();
});

it('detects parallel testing from composer.lock', function () {
    file_put_contents($this->dir.'/composer.lock', json_encode([
        'packages-dev' => [['name' => 'brianium/paratest', 'version' => 'v7.0.0']],
    ]));

    expect($this->detector->hasParallelTesting($this->dir))->toBeTrue();
});

it('detects parallel testing from an installed vendor directory', function () {
    mkdir($this->dir.'/vendor/brianium/paratest', 0o775, true);

    expect($this->detector->hasParallelTesting($this->dir))->toBeTrue();
});

it('reports parallel testing absent when paratest is nowhere', function () {
    file_put_contents($this->dir.'/composer.json', json_encode([
        'require-dev' => ['pestphp/pest' => '^3.0'],
    ]));

    expect($this->detector->hasParallelTesting($this->dir))->toBeFalse();
});

it('needs a storage link for a Laravel app without one', function () {
    mkdir($this->dir.'/storage/app/public', 0o775, true);

    expect($this->detector->needsStorageLink($this->dir))->toBeTrue();
});

it('does not need a storage link when one already exists', function () {
    mkdir($this->dir.'/storage/app/public', 0o775, true);
    mkdir($this->dir.'/public', 0o775, true);
    symlink($this->dir.'/storage/app/public', $this->dir.'/public/storage');

    expect($this->detector->needsStorageLink($this->dir))->toBeFalse();
});

it('does not need a storage link for a non-Laravel project', function () {
    expect($this->detector->needsStorageLink($this->dir))->toBeFalse();
});
