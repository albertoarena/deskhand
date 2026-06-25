<?php

declare(strict_types=1);

use Deskhand\Core\Naming\Hash;

it('is deterministic for the same value', function () {
    expect(Hash::of('feature-billing'))->toBe(Hash::of('feature-billing'));
});

it('is always non-negative', function (string $value) {
    expect(Hash::of($value))->toBeGreaterThanOrEqual(0);
})->with([
    'feature-billing',
    'a',
    '',
    'team-acme-feature-billing',
    'zzzzzzzzzzzzzzzzzzzzzzzzz',
]);

it('varies across different values', function () {
    expect(Hash::of('feature-billing'))->not->toBe(Hash::of('feature-payments'));
});
