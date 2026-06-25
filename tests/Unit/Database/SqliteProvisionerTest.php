<?php

declare(strict_types=1);

use Deskhand\Core\Database\SqliteProvisioner;

beforeEach(function () {
    $this->dir = deskhandTempDir();
    $this->provisioner = new SqliteProvisioner($this->dir);
    $this->name = 'database/deskhand/feature-billing.sqlite';
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('reports its engine', function () {
    expect($this->provisioner->engine())->toBe('sqlite');
});

it('can connect when the base directory is writable', function () {
    expect($this->provisioner->canConnect())->toBeTrue();
});

it('cannot connect when the base directory is missing', function () {
    expect((new SqliteProvisioner($this->dir.'/nope'))->canConnect())->toBeFalse();
});

it('creates the database file and its parent directories', function () {
    expect($this->provisioner->exists($this->name))->toBeFalse();

    $this->provisioner->create($this->name);

    expect($this->provisioner->exists($this->name))->toBeTrue()
        ->and(is_file($this->dir.'/'.$this->name))->toBeTrue();
});

it('creates a usable SQLite database', function () {
    $this->provisioner->create($this->name);

    $pdo = new PDO('sqlite:'.$this->dir.'/'.$this->name);
    $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO t (id) VALUES (1)');

    expect((int) $pdo->query('SELECT COUNT(*) FROM t')->fetchColumn())->toBe(1);
});

it('is idempotent and never clobbers existing data', function () {
    $this->provisioner->create($this->name);

    $pdo = new PDO('sqlite:'.$this->dir.'/'.$this->name);
    $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO t (id) VALUES (42)');
    $pdo = null;

    $this->provisioner->create($this->name);

    $pdo = new PDO('sqlite:'.$this->dir.'/'.$this->name);
    expect((int) $pdo->query('SELECT id FROM t')->fetchColumn())->toBe(42);
});

it('drops the database file', function () {
    $this->provisioner->create($this->name);

    $this->provisioner->drop($this->name);

    expect($this->provisioner->exists($this->name))->toBeFalse()
        ->and(is_file($this->dir.'/'.$this->name))->toBeFalse();
});

it('treats dropping a non-existent database as a no-op', function () {
    $this->provisioner->drop($this->name);

    expect($this->provisioner->exists($this->name))->toBeFalse();
});

it('uses an absolute name verbatim', function () {
    $absolute = $this->dir.'/elsewhere/db.sqlite';

    $this->provisioner->create($absolute);

    expect(is_file($absolute))->toBeTrue()
        ->and($this->provisioner->exists($absolute))->toBeTrue();
});
