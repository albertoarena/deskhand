<?php

declare(strict_types=1);

use Deskhand\Core\Config\Config;
use Deskhand\Core\Config\ConfigLoader;

it('returns a fully-defaulted config for an empty document', function () {
    $config = ConfigLoader::fromArray([]);

    expect($config)->toBeInstanceOf(Config::class)
        ->and($config->dbConnection)->toBeNull()
        ->and($config->servePortRange)->toBe('8300-8399')
        ->and($config->vitePortRange)->toBe('5300-5399')
        ->and($config->frontendInstall)->toBe('auto')
        ->and($config->jsPackageManager)->toBe('auto')
        ->and($config->seed)->toBeFalse()
        ->and($config->urlStrategy)->toBe('serve')
        ->and($config->urlTemplate)->toBeNull()
        ->and($config->urlDomain)->toBe('auto')
        ->and($config->migrateCommand)->toBe('php artisan migrate')
        ->and($config->seedCommand)->toBe('php artisan db:seed')
        ->and($config->testCommand)->toBe('php artisan test --parallel')
        ->and($config->postUpHooks)->toBe([])
        ->and($config->redisIsolation)->toBe('auto');
});

it('parses a full YAML document, overriding every default', function () {
    $yaml = <<<'YAML'
    db_connection: mysql
    serve_port_range: 9000-9099
    vite_port_range: 6000-6099
    frontend_install: false
    js_package_manager: yarn
    seed: true
    url_strategy: custom
    url_template: "https://{slug}.acme.test"
    url_domain: acme.test
    migrate_command: php artisan migrations
    seed_command: php artisan db:seed --class=DemoSeeder
    test_command: vendor/bin/pest
    post_up_hooks:
        - php artisan cache:clear
        - php artisan config:clear
    redis_isolation: true
    YAML;

    $config = ConfigLoader::fromString($yaml);

    expect($config->dbConnection)->toBe('mysql')
        ->and($config->servePortRange)->toBe('9000-9099')
        ->and($config->vitePortRange)->toBe('6000-6099')
        ->and($config->frontendInstall)->toBe('false')
        ->and($config->jsPackageManager)->toBe('yarn')
        ->and($config->seed)->toBeTrue()
        ->and($config->urlStrategy)->toBe('custom')
        ->and($config->urlTemplate)->toBe('https://{slug}.acme.test')
        ->and($config->urlDomain)->toBe('acme.test')
        ->and($config->migrateCommand)->toBe('php artisan migrations')
        ->and($config->seedCommand)->toBe('php artisan db:seed --class=DemoSeeder')
        ->and($config->testCommand)->toBe('vendor/bin/pest')
        ->and($config->postUpHooks)->toBe(['php artisan cache:clear', 'php artisan config:clear'])
        ->and($config->redisIsolation)->toBe('true');
});

it('merges a partial document onto the defaults', function () {
    $config = ConfigLoader::fromString("seed: true\nserve_port_range: 9000-9099");

    expect($config->seed)->toBeTrue()
        ->and($config->servePortRange)->toBe('9000-9099')
        ->and($config->vitePortRange)->toBe('5300-5399')
        ->and($config->urlStrategy)->toBe('serve');
});

it('normalises tri-state booleans to string keywords', function (mixed $given, string $expected) {
    $config = ConfigLoader::fromArray(['frontend_install' => $given, 'redis_isolation' => $given]);

    expect($config->frontendInstall)->toBe($expected)
        ->and($config->redisIsolation)->toBe($expected);
})->with([
    'true' => [true, 'true'],
    'false' => [false, 'false'],
    'auto' => ['auto', 'auto'],
]);

it('rejects an invalid url_strategy', function () {
    ConfigLoader::fromArray(['url_strategy' => 'wormhole']);
})->throws(InvalidArgumentException::class);

it('rejects an invalid js_package_manager', function () {
    ConfigLoader::fromArray(['js_package_manager' => 'pnpm']);
})->throws(InvalidArgumentException::class);

it('rejects an invalid tri-state value', function () {
    ConfigLoader::fromArray(['frontend_install' => 'maybe']);
})->throws(InvalidArgumentException::class);

it('rejects post_up_hooks that are not a list of strings', function () {
    ConfigLoader::fromArray(['post_up_hooks' => ['ok', 123]]);
})->throws(InvalidArgumentException::class);

it('rejects a top-level document that is not a mapping', function () {
    ConfigLoader::fromString('- just a list');
})->throws(InvalidArgumentException::class);

it('treats a missing file as a fully-defaulted config', function () {
    $config = ConfigLoader::fromFile('/path/that/does/not/exist/deskhand.yaml');

    expect($config->urlStrategy)->toBe('serve')
        ->and($config->seed)->toBeFalse();
});

it('treats an empty file as a fully-defaulted config', function () {
    $path = tempnam(sys_get_temp_dir(), 'deskhand-cfg');
    file_put_contents($path, '');

    try {
        $config = ConfigLoader::fromFile($path);
        expect($config->urlStrategy)->toBe('serve');
    } finally {
        @unlink($path);
    }
});

it('reads and parses a real YAML file', function () {
    $path = tempnam(sys_get_temp_dir(), 'deskhand-cfg');
    file_put_contents($path, "seed: true\nurl_strategy: herd");

    try {
        $config = ConfigLoader::fromFile($path);
        expect($config->seed)->toBeTrue()
            ->and($config->urlStrategy)->toBe('herd');
    } finally {
        @unlink($path);
    }
});
