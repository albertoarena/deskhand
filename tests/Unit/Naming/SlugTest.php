<?php

declare(strict_types=1);

use Deskhand\Core\Naming\Slug;

it('derives a filesystem- and DB-safe slug from a branch name', function (string $branch, string $expected) {
    expect(Slug::fromBranch($branch))->toBe($expected);
})->with([
    'simple feature branch' => ['feature/billing', 'feature-billing'],
    'already slug-shaped' => ['feature-billing', 'feature-billing'],
    'uppercase is lowercased' => ['Feature/Billing', 'feature-billing'],
    'dots and versions' => ['release/v1.2.0', 'release-v1-2-0'],
    'collapses repeated separators' => ['feature//billing__ui', 'feature-billing-ui'],
    'trims leading and trailing separators' => ['/feature/billing/', 'feature-billing'],
    'nested namespaces' => ['team/acme/feature/billing', 'team-acme-feature-billing'],
]);

it('is deterministic for the same branch', function () {
    expect(Slug::fromBranch('feature/billing'))->toBe(Slug::fromBranch('feature/billing'));
});

it('derives the same slug for branches that normalise identically', function () {
    expect(Slug::fromBranch('feature/billing'))->toBe(Slug::fromBranch('feature-billing'));
});
