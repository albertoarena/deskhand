<?php

declare(strict_types=1);

use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Planning\PlanRequest;
use Deskhand\Core\Planning\WorktreePlanner;
use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Exception\WorktreeExistsException;
use Deskhand\Tests\Fakes\FakeRegistry;

function planRequest(array $overrides = []): PlanRequest
{
    return new PlanRequest(
        branch: $overrides['branch'] ?? 'feature/billing',
        repoRoot: $overrides['repoRoot'] ?? '/repo',
        engine: $overrides['engine'] ?? 'sqlite',
        shared: $overrides['shared'] ?? false,
        redisIsolated: $overrides['redisIsolated'] ?? false,
        testDbCount: $overrides['testDbCount'] ?? 4,
        pathFlag: $overrides['pathFlag'] ?? null,
        urlFlag: $overrides['urlFlag'] ?? null,
        baseEnv: $overrides['baseEnv'] ?? ['DB_DATABASE' => 'acme'],
        createdAt: $overrides['createdAt'] ?? '2026-06-25T10:00:00Z',
    );
}

function planner(?FakeRegistry $registry = null, array $config = []): WorktreePlanner
{
    $config = ConfigLoader::fromArray($config);

    return new WorktreePlanner($config, new UrlResolver($config), $registry ?? new FakeRegistry);
}

it('plans an isolated SQLite worktree', function () {
    $record = planner()->plan(planRequest());

    expect($record)->toBeInstanceOf(WorktreeRecord::class)
        ->and($record->slug)->toBe('feature-billing')
        ->and($record->branch)->toBe('feature/billing')
        ->and($record->path)->toBe('.claude/worktrees/feature-billing')
        ->and($record->createdAt)->toBe('2026-06-25T10:00:00Z')
        ->and($record->db->engine)->toBe('sqlite')
        ->and($record->db->shared)->toBeFalse()
        ->and($record->db->main)->toBe('database/deskhand/feature-billing.sqlite')
        ->and($record->db->testDbs)->toBe([])
        ->and($record->ports->serve)->toBeGreaterThanOrEqual(8300)->toBeLessThanOrEqual(8399)
        ->and($record->ports->vite)->toBeGreaterThanOrEqual(5300)->toBeLessThanOrEqual(5399)
        ->and($record->redis->isolated)->toBeFalse()
        ->and($record->url)->toBe('http://127.0.0.1:'.$record->ports->serve);
});

it('plans an isolated MySQL worktree with numbered test databases', function () {
    $record = planner()->plan(planRequest(['engine' => 'mysql', 'testDbCount' => 2]));

    expect($record->db->engine)->toBe('mysql')
        ->and($record->db->main)->toBe('acme_wt_feature-billing')
        ->and($record->db->testDbs)->toBe([
            'acme_wt_feature-billing_test_1',
            'acme_wt_feature-billing_test_2',
        ]);
});

it('honours the configured test database count', function () {
    $record = planner()->plan(planRequest(['engine' => 'mysql', 'testDbCount' => 8]));

    expect($record->db->testDbs)->toHaveCount(8);
});

it('plans a shared database without isolation or test databases', function () {
    $record = planner()->plan(planRequest(['engine' => 'mysql', 'shared' => true]));

    expect($record->db->shared)->toBeTrue()
        ->and($record->db->main)->toBe('acme')
        ->and($record->db->testDbs)->toBe([]);
});

it('populates Redis allocation when isolation is active', function () {
    $record = planner()->plan(planRequest(['redisIsolated' => true]));

    expect($record->redis->isolated)->toBeTrue()
        ->and($record->redis->prefix)->toBe('feature-billing:')
        ->and($record->redis->dbIndex)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(15);
});

it('uses the path override', function () {
    $record = planner()->plan(planRequest(['pathFlag' => '/custom/location']));

    expect($record->path)->toBe('/custom/location');
});

it('uses the url override ahead of the strategy', function () {
    $record = planner()->plan(planRequest(['urlFlag' => 'https://billing.acme.test']));

    expect($record->url)->toBe('https://billing.acme.test');
});

it('fails on a slug collision with a different branch', function () {
    $registry = new FakeRegistry;
    $registry->save(new WorktreeRecord(
        slug: 'feature-billing',
        branch: 'feature-billing', // a different branch that derives the same slug
        path: '.claude/worktrees/feature-billing',
        createdAt: '2026-06-24T10:00:00Z',
        db: new DatabaseRecord(engine: 'sqlite', shared: false, main: 'x'),
        ports: new PortAllocation(serve: 8312, vite: 5312),
        redis: new RedisAllocation(isolated: false),
        url: 'http://127.0.0.1:8312',
    ));

    planner($registry)->plan(planRequest(['branch' => 'feature/billing']));
})->throws(WorktreeExistsException::class);

it('allows an idempotent re-run for the same branch', function () {
    $registry = new FakeRegistry;
    $registry->save(planner()->plan(planRequest()));

    $record = planner($registry)->plan(planRequest());

    expect($record->slug)->toBe('feature-billing');
});

it('derives the MySQL base name from the project directory when DB_DATABASE is absent', function () {
    $record = planner()->plan(planRequest(['engine' => 'mysql', 'repoRoot' => '/srv/myshop', 'baseEnv' => []]));

    expect($record->db->main)->toBe('myshop_wt_feature-billing');
});
