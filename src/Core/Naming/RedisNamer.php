<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

use Deskhand\Core\Registry\RedisAllocation;

/**
 * Conditional Redis namespacing (§7). The per-slug key prefix is the primary,
 * effectively-unlimited isolation mechanism. The logical DB index is a best-
 * effort bonus derived as `hash(slug) % 16`; index collisions are tolerated,
 * never a failure, and deskhand never scans for a free index (that would break
 * determinism).
 */
final class RedisNamer
{
    public const int DB_COUNT = 16;

    public function forSlug(string $slug, bool $isolated): RedisAllocation
    {
        if (! $isolated) {
            return new RedisAllocation(isolated: false);
        }

        return new RedisAllocation(
            isolated: true,
            prefix: "{$slug}:",
            dbIndex: Hash::of($slug) % self::DB_COUNT,
        );
    }
}
