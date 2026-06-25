<?php

declare(strict_types=1);

use Deskhand\Core\Env\DotenvMaterializer;

beforeEach(function () {
    $this->dir = deskhandTempDir();
    $this->base = $this->dir.'/.env';
    $this->target = $this->dir.'/worktree/.env';
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('reads key/value pairs, ignoring comments and blank lines', function () {
    file_put_contents($this->base, <<<'ENV'
    # a comment
    APP_NAME=Laravel

    APP_ENV=local
    ENV);

    expect((new DotenvMaterializer)->read($this->base))
        ->toBe(['APP_NAME' => 'Laravel', 'APP_ENV' => 'local']);
});

it('strips surrounding single and double quotes', function () {
    file_put_contents($this->base, "A=\"hello world\"\nB='single quoted'\nC=plain");

    expect((new DotenvMaterializer)->read($this->base))
        ->toBe(['A' => 'hello world', 'B' => 'single quoted', 'C' => 'plain']);
});

it('preserves = inside values and empty values', function () {
    file_put_contents($this->base, "APP_KEY=base64:abcd1234==\nEMPTY=");

    expect((new DotenvMaterializer)->read($this->base))
        ->toBe(['APP_KEY' => 'base64:abcd1234==', 'EMPTY' => '']);
});

it('honours an optional export prefix', function () {
    file_put_contents($this->base, 'export APP_ENV=production');

    expect((new DotenvMaterializer)->read($this->base))->toBe(['APP_ENV' => 'production']);
});

it('returns an empty map for a missing file', function () {
    expect((new DotenvMaterializer)->read($this->dir.'/nope.env'))->toBe([]);
});

it('replaces existing keys in place and appends new ones', function () {
    file_put_contents($this->base, "APP_NAME=Laravel\nAPP_ENV=local\nDB_DATABASE=acme");

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, [
        'DB_DATABASE' => 'database/deskhand/feature-billing.sqlite',
        'DESKHAND_SLUG' => 'feature-billing',
    ]);

    $written = (new DotenvMaterializer)->read($this->target);

    expect($written['APP_NAME'])->toBe('Laravel')
        ->and($written['APP_ENV'])->toBe('local')
        ->and($written['DB_DATABASE'])->toBe('database/deskhand/feature-billing.sqlite')
        ->and($written['DESKHAND_SLUG'])->toBe('feature-billing');
});

it('preserves comments, blank lines and untouched values verbatim', function () {
    file_put_contents($this->base, <<<'ENV'
    # Application
    APP_NAME=Laravel
    APP_KEY=base64:abcd1234==

    DB_DATABASE=acme
    ENV);

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, ['DB_DATABASE' => 'isolated']);

    $contents = (string) file_get_contents($this->target);

    expect($contents)->toContain('# Application')
        ->and($contents)->toContain('APP_KEY=base64:abcd1234==')
        ->and($contents)->toContain('DB_DATABASE=isolated')
        ->and($contents)->not->toContain('DB_DATABASE=acme');
});

it('quotes override values that contain spaces or brackets', function () {
    file_put_contents($this->base, 'APP_NAME=Laravel');

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, ['APP_NAME' => 'Acme [feature-billing]']);

    $contents = (string) file_get_contents($this->target);

    expect($contents)->toContain('APP_NAME="Acme [feature-billing]"')
        ->and((new DotenvMaterializer)->read($this->target)['APP_NAME'])->toBe('Acme [feature-billing]');
});

it('leaves URL- and path-like values unquoted', function () {
    file_put_contents($this->base, 'APP_URL=http://localhost');

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, ['APP_URL' => 'http://feature-billing.test']);

    expect((string) file_get_contents($this->target))->toContain('APP_URL=http://feature-billing.test');
});

it('creates the target parent directory', function () {
    file_put_contents($this->base, 'APP_NAME=Laravel');

    expect(is_dir(dirname($this->target)))->toBeFalse();

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, []);

    expect(is_file($this->target))->toBeTrue();
});

it('writes a real file, never a symlink, and never through an existing symlink', function () {
    file_put_contents($this->base, "APP_NAME=Laravel\nSECRET=base-secret");
    mkdir(dirname($this->target), 0o775, true);
    symlink($this->base, $this->target); // hostile pre-existing symlink to the base

    (new DotenvMaterializer)->writeEnv($this->base, $this->target, ['APP_NAME' => 'Worktree']);

    expect(is_link($this->target))->toBeFalse()
        ->and(file_get_contents($this->base))->toBe("APP_NAME=Laravel\nSECRET=base-secret")
        ->and((new DotenvMaterializer)->read($this->target)['APP_NAME'])->toBe('Worktree');
});

it('writes only the overrides when the base file is absent', function () {
    (new DotenvMaterializer)->writeEnv($this->dir.'/missing.env', $this->target, ['CACHE_STORE' => 'array']);

    expect((new DotenvMaterializer)->read($this->target))->toBe(['CACHE_STORE' => 'array']);
});
