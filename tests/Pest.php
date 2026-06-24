<?php

declare(strict_types=1);

use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;

/*
 * Pest configuration.
 *
 * As subsystems land (later build phases), bind the base TestCase and shared
 * helpers here, e.g.:
 *   uses(Deskhand\Tests\TestCase::class)->in('Unit', 'Integration');
 */

/**
 * A canonical SQLite worktree record matching the §5.1 example shape,
 * shared across registry/record tests.
 */
function sampleRecord(): WorktreeRecord
{
    return new WorktreeRecord(
        slug: 'feature-billing',
        branch: 'feature/billing',
        path: '.claude/worktrees/feature-billing',
        createdAt: '2026-06-24T10:00:00Z',
        db: new DatabaseRecord(
            engine: 'sqlite',
            shared: false,
            main: 'database/deskhand/feature-billing.sqlite',
            testDbs: [],
        ),
        ports: new PortAllocation(serve: 8312, vite: 5312),
        redis: new RedisAllocation(isolated: false, prefix: null, dbIndex: null),
        url: 'http://127.0.0.1:8312',
    );
}
