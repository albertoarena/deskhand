<?php

declare(strict_types=1);

namespace Deskhand\Core\Url;

use Deskhand\Core\Config\Config;
use Deskhand\Exception\DeskhandException;

/**
 * Resolves the reported worktree URL by the §7.1 precedence chain: the `--url`
 * flag wins, then a custom template, then a herd/valet host (explicit or
 * APP_URL-derived domain), else the serve URL. Generic — it only computes and
 * reports the URL; deskhand never runs `herd link`/`valet link`.
 */
final class UrlResolver
{
    private const string FALLBACK_DOMAIN = 'test';

    public function __construct(private readonly Config $config) {}

    /**
     * @param  array<string, string>  $baseEnv
     */
    public function resolve(?string $urlFlag, string $slug, int $servePort, array $baseEnv): string
    {
        if ($urlFlag !== null) {
            return $this->substitute($urlFlag, $slug, $servePort);
        }

        return match ($this->config->urlStrategy) {
            'custom' => $this->custom($slug, $servePort),
            'herd', 'valet' => 'http://'.$slug.'.'.$this->domain($baseEnv),
            default => 'http://127.0.0.1:'.$servePort,
        };
    }

    private function custom(string $slug, int $servePort): string
    {
        if ($this->config->urlTemplate === null) {
            throw new DeskhandException("url_strategy is 'custom' but url_template is not set; provide a url_template or choose another strategy.");
        }

        return $this->substitute($this->config->urlTemplate, $slug, $servePort);
    }

    /**
     * @param  array<string, string>  $baseEnv
     */
    private function domain(array $baseEnv): string
    {
        if ($this->config->urlDomain !== 'auto') {
            return $this->config->urlDomain;
        }

        $appUrl = $baseEnv['APP_URL'] ?? '';
        $host = $appUrl === '' ? null : parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return self::FALLBACK_DOMAIN;
        }

        $labels = explode('.', $host);

        // Drop the base app's own leftmost subdomain; single-label hosts
        // (e.g. localhost) are used as-is.
        if (count($labels) > 1) {
            array_shift($labels);
        }

        return implode('.', $labels);
    }

    private function substitute(string $template, string $slug, int $servePort): string
    {
        return str_replace(['{slug}', '{port}'], [$slug, (string) $servePort], $template);
    }
}
