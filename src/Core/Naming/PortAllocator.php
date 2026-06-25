<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

use Deskhand\Core\Registry\PortAllocation;
use InvalidArgumentException;

/**
 * Deterministically maps a slug to serve and Vite ports inside the configured
 * ranges (§7). Same branch → same ports every time; this is a hash, never a
 * free-port scan. A foreign process occupying a derived port is reported by
 * the caller (`status`/`up`), not silently reassigned here.
 */
final class PortAllocator
{
    /** @var array{int, int} */
    private readonly array $serveRange;

    /** @var array{int, int} */
    private readonly array $viteRange;

    public function __construct(string $serveRange, string $viteRange)
    {
        $this->serveRange = self::parseRange($serveRange);
        $this->viteRange = self::parseRange($viteRange);
    }

    public function forSlug(string $slug): PortAllocation
    {
        return new PortAllocation(
            serve: $this->derive('serve', $slug, $this->serveRange),
            vite: $this->derive('vite', $slug, $this->viteRange),
        );
    }

    /**
     * @param  array{int, int}  $range
     */
    private function derive(string $service, string $slug, array $range): int
    {
        [$start, $end] = $range;
        $span = $end - $start + 1;

        // Salt with the service so serve/vite are decorrelated within a slug.
        return $start + (Hash::of($service.':'.$slug) % $span);
    }

    /**
     * @return array{int, int}
     */
    private static function parseRange(string $range): array
    {
        if (preg_match('/^(\d+)-(\d+)$/', $range, $m) !== 1) {
            throw new InvalidArgumentException("Invalid port range '{$range}'; expected 'start-end' (e.g. 8300-8399).");
        }

        $start = (int) $m[1];
        $end = (int) $m[2];

        if ($start > $end) {
            throw new InvalidArgumentException("Invalid port range '{$range}'; start must not exceed end.");
        }

        return [$start, $end];
    }
}
