<?php

declare(strict_types=1);

use Deskhand\Core\Naming\PortAllocator;
use Deskhand\Core\Registry\PortAllocation;

it('derives serve and vite ports within their configured ranges', function () {
    $allocator = new PortAllocator('8300-8399', '5300-5399');

    $ports = $allocator->forSlug('feature-billing');

    expect($ports)->toBeInstanceOf(PortAllocation::class)
        ->and($ports->serve)->toBeGreaterThanOrEqual(8300)->toBeLessThanOrEqual(8399)
        ->and($ports->vite)->toBeGreaterThanOrEqual(5300)->toBeLessThanOrEqual(5399);
});

it('is deterministic: same slug always yields the same ports', function () {
    $allocator = new PortAllocator('8300-8399', '5300-5399');

    expect($allocator->forSlug('feature-billing'))->toEqual($allocator->forSlug('feature-billing'));
});

it('spreads different slugs across the range', function () {
    $allocator = new PortAllocator('8300-8399', '5300-5399');

    $a = $allocator->forSlug('feature-billing');
    $b = $allocator->forSlug('feature-payments');

    expect([$a->serve, $a->vite])->not->toBe([$b->serve, $b->vite]);
});

it('honours a single-port range', function () {
    $allocator = new PortAllocator('8300-8300', '5300-5300');

    $ports = $allocator->forSlug('anything');

    expect($ports->serve)->toBe(8300)
        ->and($ports->vite)->toBe(5300);
});

it('rejects a malformed range', function () {
    new PortAllocator('not-a-range', '5300-5399');
})->throws(InvalidArgumentException::class);

it('rejects an inverted range', function () {
    new PortAllocator('8399-8300', '5300-5399');
})->throws(InvalidArgumentException::class);
