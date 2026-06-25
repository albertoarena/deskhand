<?php

declare(strict_types=1);

use Deskhand\Core\Naming\RedisNamer;
use Deskhand\Core\Registry\RedisAllocation;

it('derives a per-slug prefix and a db index in 0..15 when isolated', function () {
    $allocation = (new RedisNamer)->forSlug('feature-billing', isolated: true);

    expect($allocation)->toBeInstanceOf(RedisAllocation::class)
        ->and($allocation->isolated)->toBeTrue()
        ->and($allocation->prefix)->toContain('feature-billing')
        ->and($allocation->dbIndex)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(15);
});

it('is deterministic: same slug yields the same prefix and index', function () {
    $namer = new RedisNamer;

    expect($namer->forSlug('feature-billing', isolated: true))
        ->toEqual($namer->forSlug('feature-billing', isolated: true));
});

it('returns an inactive allocation with null fields when isolation is off', function () {
    $allocation = (new RedisNamer)->forSlug('feature-billing', isolated: false);

    expect($allocation->isolated)->toBeFalse()
        ->and($allocation->prefix)->toBeNull()
        ->and($allocation->dbIndex)->toBeNull();
});
