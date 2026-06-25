<?php

declare(strict_types=1);

use Deskhand\Core\Naming\AppNameTag;

it('tags the base APP_NAME with the slug', function () {
    expect(AppNameTag::make('Acme', 'feature-billing', 'acme-project'))
        ->toBe('Acme [feature-billing]');
});

it('falls back to the project directory name when APP_NAME is absent', function () {
    expect(AppNameTag::make(null, 'feature-billing', 'acme-project'))
        ->toBe('acme-project [feature-billing]');
});

it('falls back when APP_NAME is empty or whitespace', function (?string $base) {
    expect(AppNameTag::make($base, 'feature-billing', 'acme-project'))
        ->toBe('acme-project [feature-billing]');
})->with([
    'empty string' => [''],
    'whitespace only' => ['   '],
]);

it('never emits a leading space or empty name', function () {
    $tag = AppNameTag::make('', 'feature-billing', 'acme-project');

    expect($tag)->not->toStartWith(' ')
        ->and($tag)->not->toStartWith('[');
});
