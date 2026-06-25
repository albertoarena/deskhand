<?php

declare(strict_types=1);

use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Exception\DeskhandException;

function resolver(array $config = []): UrlResolver
{
    return new UrlResolver(ConfigLoader::fromArray($config));
}

it('uses the --url flag verbatim, ahead of any strategy', function () {
    $url = resolver(['url_strategy' => 'serve'])->resolve('https://billing.acme.test', 'feature-billing', 8312, []);

    expect($url)->toBe('https://billing.acme.test');
});

it('substitutes {slug} and {port} in the --url flag', function () {
    $url = resolver()->resolve('https://{slug}.acme.test:{port}', 'feature-billing', 8312, []);

    expect($url)->toBe('https://feature-billing.acme.test:8312');
});

it('defaults to the serve strategy at 127.0.0.1 with the serve port', function () {
    expect(resolver()->resolve(null, 'feature-billing', 8312, []))->toBe('http://127.0.0.1:8312');
});

it('renders the custom template with substitutions', function () {
    $url = resolver(['url_strategy' => 'custom', 'url_template' => 'https://{slug}.acme.test'])
        ->resolve(null, 'feature-billing', 8312, []);

    expect($url)->toBe('https://feature-billing.acme.test');
});

it('fails when the custom strategy has no template', function () {
    resolver(['url_strategy' => 'custom'])->resolve(null, 'feature-billing', 8312, []);
})->throws(DeskhandException::class);

it('builds a herd URL with an explicit domain', function () {
    $url = resolver(['url_strategy' => 'herd', 'url_domain' => 'acme.test'])
        ->resolve(null, 'feature-billing', 8312, []);

    expect($url)->toBe('http://feature-billing.acme.test');
});

it('auto-detects the domain from APP_URL by dropping the leftmost label', function (string $appUrl, string $expected) {
    $url = resolver(['url_strategy' => 'herd'])->resolve(null, 'feature-billing', 8312, ['APP_URL' => $appUrl]);

    expect($url)->toBe($expected);
})->with([
    'two labels' => ['http://myapp.test', 'http://feature-billing.test'],
    'three labels' => ['http://app.acme.dev', 'http://feature-billing.acme.dev'],
    'single label used as-is' => ['http://localhost', 'http://feature-billing.localhost'],
]);

it('falls back to the test domain when APP_URL is absent or unparseable', function (array $baseEnv) {
    $url = resolver(['url_strategy' => 'herd'])->resolve(null, 'feature-billing', 8312, $baseEnv);

    expect($url)->toBe('http://feature-billing.test');
})->with([
    'absent' => [[]],
    'empty' => [['APP_URL' => '']],
    'no host' => [['APP_URL' => 'not a url']],
]);

it('treats valet like herd', function () {
    $url = resolver(['url_strategy' => 'valet', 'url_domain' => 'acme.test'])
        ->resolve(null, 'feature-billing', 8312, []);

    expect($url)->toBe('http://feature-billing.acme.test');
});
