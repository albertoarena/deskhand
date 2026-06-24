<?php

declare(strict_types=1);

use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;

it('serialises to the registry record shape (§5.1)', function () {
    expect(sampleRecord()->toArray())->toBe([
        'slug' => 'feature-billing',
        'branch' => 'feature/billing',
        'path' => '.claude/worktrees/feature-billing',
        'created_at' => '2026-06-24T10:00:00Z',
        'db' => [
            'engine' => 'sqlite',
            'shared' => false,
            'main' => 'database/deskhand/feature-billing.sqlite',
            'test_dbs' => [],
        ],
        'ports' => ['serve' => 8312, 'vite' => 5312],
        'redis' => ['isolated' => false, 'prefix' => null, 'db_index' => null],
        'url' => 'http://127.0.0.1:8312',
    ]);
});

it('round-trips through fromArray/toArray', function () {
    $array = sampleRecord()->toArray();

    expect(WorktreeRecord::fromArray($array)->toArray())->toBe($array);
});

it('carries mysql test database names', function () {
    $record = new WorktreeRecord(
        slug: 'feature-billing',
        branch: 'feature/billing',
        path: '.claude/worktrees/feature-billing',
        createdAt: '2026-06-24T10:00:00Z',
        db: new DatabaseRecord(
            engine: 'mysql',
            shared: false,
            main: 'acme_wt_feature-billing',
            testDbs: ['acme_wt_feature-billing_test_1', 'acme_wt_feature-billing_test_2'],
        ),
        ports: new PortAllocation(serve: 8312, vite: 5312),
        redis: new RedisAllocation(isolated: true, prefix: 'feature-billing:', dbIndex: 3),
        url: 'http://feature-billing.test',
    );

    $array = $record->toArray();

    expect($array['db']['engine'])->toBe('mysql')
        ->and($array['db']['test_dbs'])->toBe(['acme_wt_feature-billing_test_1', 'acme_wt_feature-billing_test_2'])
        ->and($array['redis'])->toBe(['isolated' => true, 'prefix' => 'feature-billing:', 'db_index' => 3]);
});
