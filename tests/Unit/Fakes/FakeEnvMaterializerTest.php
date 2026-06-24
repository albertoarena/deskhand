<?php

declare(strict_types=1);

use Deskhand\Tests\Fakes\FakeEnvMaterializer;

it('reads a seeded base env as a key/value map', function () {
    $env = new FakeEnvMaterializer;
    $env->seed('/base/.env', ['APP_NAME' => 'Acme', 'DB_DATABASE' => 'acme']);

    expect($env->read('/base/.env'))->toBe(['APP_NAME' => 'Acme', 'DB_DATABASE' => 'acme']);
});

it('writes a target env merging overrides over the base', function () {
    $env = new FakeEnvMaterializer;
    $env->seed('/base/.env', ['APP_NAME' => 'Acme', 'DB_DATABASE' => 'acme', 'APP_KEY' => 'base']);

    $env->writeEnv('/base/.env', '/wt/.env', [
        'DB_DATABASE' => 'database/deskhand/feature-billing.sqlite',
        'APP_NAME' => 'Acme [feature-billing]',
    ]);

    expect($env->read('/wt/.env'))->toBe([
        'APP_NAME' => 'Acme [feature-billing]',
        'DB_DATABASE' => 'database/deskhand/feature-billing.sqlite',
        'APP_KEY' => 'base',
    ])->and($env->written)->toContain('/wt/.env');
});
