<?php

declare(strict_types=1);

use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\JsonRegistry;
use Deskhand\Core\Registry\PortAllocation;
use Deskhand\Core\Registry\RedisAllocation;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Exception\RegistryException;

beforeEach(function () {
    $this->dir = deskhandTempDir();
    $this->path = JsonRegistry::pathFor($this->dir);
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

function mysqlRecord(): WorktreeRecord
{
    return new WorktreeRecord(
        slug: 'feature-payments',
        branch: 'feature/payments',
        path: '.claude/worktrees/feature-payments',
        createdAt: '2026-06-24T11:00:00Z',
        db: new DatabaseRecord(
            engine: 'mysql',
            shared: false,
            main: 'acme_wt_feature-payments',
            testDbs: ['acme_wt_feature-payments_test_1', 'acme_wt_feature-payments_test_2'],
        ),
        ports: new PortAllocation(serve: 8350, vite: 5350),
        redis: new RedisAllocation(isolated: true, prefix: 'feature-payments:', dbIndex: 7),
        url: 'http://feature-payments.test',
    );
}

it('builds the fixed §5 registry path from a repo root', function () {
    expect(JsonRegistry::pathFor('/repo'))->toBe('/repo/.claude/deskhand/registry.json');
});

it('returns an empty list when the file does not exist', function () {
    expect((new JsonRegistry($this->path))->all())->toBe([]);
});

it('persists a saved record across instances', function () {
    (new JsonRegistry($this->path))->save(sampleRecord());

    $reloaded = (new JsonRegistry($this->path))->all();

    expect($reloaded)->toHaveCount(1)
        ->and($reloaded[0]->slug)->toBe('feature-billing')
        ->and($reloaded[0]->url)->toBe('http://127.0.0.1:8312');
});

it('finds a record by slug or by branch', function () {
    $registry = new JsonRegistry($this->path);
    $registry->save(sampleRecord());

    expect($registry->find('feature-billing'))->not->toBeNull()
        ->and($registry->find('feature/billing'))->not->toBeNull()
        ->and($registry->find('feature/billing')->slug)->toBe('feature-billing')
        ->and($registry->find('nope'))->toBeNull();
});

it('upserts by slug without duplicating entries', function () {
    $registry = new JsonRegistry($this->path);
    $registry->save(sampleRecord());
    $registry->save(sampleRecord());

    expect($registry->all())->toHaveCount(1);
});

it('updates an existing record in place', function () {
    $registry = new JsonRegistry($this->path);
    $registry->save(sampleRecord());

    $updated = new WorktreeRecord(
        slug: 'feature-billing',
        branch: 'feature/billing',
        path: '.claude/worktrees/feature-billing',
        createdAt: '2026-06-24T10:00:00Z',
        db: new DatabaseRecord(engine: 'sqlite', shared: false, main: 'database/deskhand/feature-billing.sqlite'),
        ports: new PortAllocation(serve: 8312, vite: 5312),
        redis: new RedisAllocation(isolated: false),
        url: 'http://feature-billing.test',
    );
    $registry->save($updated);

    expect($registry->all())->toHaveCount(1)
        ->and($registry->find('feature-billing')->url)->toBe('http://feature-billing.test');
});

it('removes a record by slug', function () {
    $registry = new JsonRegistry($this->path);
    $registry->save(sampleRecord());

    $registry->remove('feature-billing');

    expect($registry->all())->toBeEmpty()
        ->and($registry->find('feature-billing'))->toBeNull();
});

it('treats removing an unknown slug as a no-op', function () {
    $registry = new JsonRegistry($this->path);

    $registry->remove('does-not-exist');

    expect($registry->all())->toBe([]);
});

it('creates the registry parent directory on first save', function () {
    expect(is_dir(dirname($this->path)))->toBeFalse();

    (new JsonRegistry($this->path))->save(sampleRecord());

    expect(is_file($this->path))->toBeTrue();
});

it('round-trips a MySQL record with test databases', function () {
    (new JsonRegistry($this->path))->save(mysqlRecord());

    $record = (new JsonRegistry($this->path))->find('feature-payments');

    expect($record->db->engine)->toBe('mysql')
        ->and($record->db->main)->toBe('acme_wt_feature-payments')
        ->and($record->db->testDbs)->toBe(['acme_wt_feature-payments_test_1', 'acme_wt_feature-payments_test_2'])
        ->and($record->redis->isolated)->toBeTrue()
        ->and($record->redis->prefix)->toBe('feature-payments:')
        ->and($record->redis->dbIndex)->toBe(7);
});

it('keeps multiple records', function () {
    $registry = new JsonRegistry($this->path);
    $registry->save(sampleRecord());
    $registry->save(mysqlRecord());

    expect($registry->all())->toHaveCount(2)
        ->and($registry->find('feature-billing'))->not->toBeNull()
        ->and($registry->find('feature-payments'))->not->toBeNull();
});

it('writes valid, decodable JSON to disk', function () {
    (new JsonRegistry($this->path))->save(sampleRecord());

    $decoded = json_decode((string) file_get_contents($this->path), true);

    expect($decoded)->toBeArray()
        ->and($decoded[0]['slug'])->toBe('feature-billing')
        ->and($decoded[0]['db']['engine'])->toBe('sqlite');
});

it('treats an empty file as an empty registry', function () {
    mkdir(dirname($this->path), 0o775, true);
    file_put_contents($this->path, '');

    expect((new JsonRegistry($this->path))->all())->toBe([]);
});

it('fails loudly on a corrupt registry file', function () {
    mkdir(dirname($this->path), 0o775, true);
    file_put_contents($this->path, '{ this is not json');

    (new JsonRegistry($this->path))->all();
})->throws(RegistryException::class);
