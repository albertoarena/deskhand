<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeRegistry;

it('finds a saved record by slug or by branch', function () {
    $registry = new FakeRegistry;
    $registry->save(sampleRecord());

    expect($registry->find('feature-billing'))->not->toBeNull()
        ->and($registry->find('feature/billing'))->not->toBeNull()
        ->and($registry->find('feature/billing')->slug)->toBe('feature-billing')
        ->and($registry->find('nope'))->toBeNull();
});

it('upserts by slug without duplicating entries', function () {
    $registry = new FakeRegistry;
    $registry->save(sampleRecord());
    $registry->save(sampleRecord());

    expect($registry->all())->toHaveCount(1);
});

it('removes a record by slug', function () {
    $registry = new FakeRegistry;
    $registry->save(sampleRecord());

    $registry->remove('feature-billing');

    expect($registry->all())->toBeEmpty()
        ->and($registry->find('feature-billing'))->toBeNull();
});
