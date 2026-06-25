<?php

declare(strict_types=1);

use Deskhand\Core\Naming\DatabaseNamer;

it('builds SQLite main paths inside the deskhand directory without a _wt_ infix', function () {
    $namer = new DatabaseNamer;

    expect($namer->main('sqlite', 'feature-billing', 'acme'))
        ->toBe('database/deskhand/feature-billing.sqlite');
});

it('builds SQLite test paths', function () {
    $namer = new DatabaseNamer;

    expect($namer->test('sqlite', 'feature-billing', 'acme', 3))
        ->toBe('database/deskhand/feature-billing_test_3.sqlite');
});

it('builds MySQL main names with the _wt_ infix off the base database name', function () {
    $namer = new DatabaseNamer;

    expect($namer->main('mysql', 'feature-billing', 'acme'))
        ->toBe('acme_wt_feature-billing');
});

it('builds MySQL test names with the _wt_ infix', function () {
    $namer = new DatabaseNamer;

    expect($namer->test('mysql', 'feature-billing', 'acme', 2))
        ->toBe('acme_wt_feature-billing_test_2');
});

it('rejects an unknown engine', function () {
    (new DatabaseNamer)->main('postgres', 'feature-billing', 'acme');
})->throws(InvalidArgumentException::class);
