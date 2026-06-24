<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;

it('creates and drops databases, tracking existence', function () {
    $db = new FakeDatabaseProvisioner('sqlite');

    expect($db->engine())->toBe('sqlite')
        ->and($db->exists('main.sqlite'))->toBeFalse();

    $db->create('main.sqlite');
    expect($db->exists('main.sqlite'))->toBeTrue()
        ->and($db->created)->toBe(['main.sqlite']);

    $db->drop('main.sqlite');
    expect($db->exists('main.sqlite'))->toBeFalse()
        ->and($db->dropped)->toBe(['main.sqlite']);
});

it('reports connectivity, configurable for failure cases', function () {
    $db = new FakeDatabaseProvisioner('mysql');
    expect($db->canConnect())->toBeTrue();

    $db->connectable = false;
    expect($db->canConnect())->toBeFalse();
});
