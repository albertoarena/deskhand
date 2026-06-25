<?php

declare(strict_types=1);

use Deskhand\Core\Database\MysqlProvisioner;
use Deskhand\Exception\DatabaseProvisionException;

/**
 * Integration tests against a real MySQL server. They are skipped unless
 * DESKHAND_TEST_MYSQL_* environment variables are configured, so CI and
 * contributors without a server stay green. The configured user only needs
 * create/drop rights on a `deskhand_test_%` prefix; each test makes a uniquely
 * named database under that prefix and drops it in teardown.
 */
function mysqlEnv(string $key, string $default = ''): string
{
    $value = getenv('DESKHAND_TEST_MYSQL_'.$key);

    return $value === false ? $default : $value;
}

beforeEach(function () {
    if (mysqlEnv('USER') === '') {
        $this->markTestSkipped('Set DESKHAND_TEST_MYSQL_USER/PASSWORD/HOST/PORT to run MySQL integration tests.');
    }

    $this->host = mysqlEnv('HOST', '127.0.0.1');
    $this->port = (int) mysqlEnv('PORT', '3306');
    $this->user = mysqlEnv('USER');
    $this->password = mysqlEnv('PASSWORD');

    $this->provisioner = new MysqlProvisioner($this->host, $this->port, $this->user, $this->password);
    $this->name = 'deskhand_test_wt_'.bin2hex(random_bytes(5));
});

afterEach(function () {
    if (isset($this->provisioner, $this->name)) {
        $this->provisioner->drop($this->name);
    }
});

it('reports its engine', function () {
    expect($this->provisioner->engine())->toBe('mysql');
});

it('connects with valid credentials', function () {
    expect($this->provisioner->canConnect())->toBeTrue();
});

it('reports it cannot connect with bad credentials', function () {
    $bad = new MysqlProvisioner($this->host, $this->port, $this->user, $this->password.'-wrong');

    expect($bad->canConnect())->toBeFalse();
});

it('creates a database and detects its existence', function () {
    expect($this->provisioner->exists($this->name))->toBeFalse();

    $this->provisioner->create($this->name);

    expect($this->provisioner->exists($this->name))->toBeTrue();
});

it('drops a database', function () {
    $this->provisioner->create($this->name);

    $this->provisioner->drop($this->name);

    expect($this->provisioner->exists($this->name))->toBeFalse();
});

it('is idempotent and never clobbers existing data', function () {
    $this->provisioner->create($this->name);

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $this->host, $this->port, $this->name);
    $pdo = new PDO($dsn, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE t (id INT PRIMARY KEY)');
    $pdo->exec('INSERT INTO t (id) VALUES (7)');
    $pdo = null;

    $this->provisioner->create($this->name); // must not drop/recreate

    $pdo = new PDO($dsn, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    expect((int) $pdo->query('SELECT id FROM t')->fetchColumn())->toBe(7);
});

it('treats dropping a non-existent database as a no-op', function () {
    $this->provisioner->drop($this->name);

    expect($this->provisioner->exists($this->name))->toBeFalse();
});

it('refuses an unsafe database name', function () {
    $this->provisioner->create('deskhand_test_wt_x`; DROP DATABASE other;');
})->throws(DatabaseProvisionException::class);
