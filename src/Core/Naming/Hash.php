<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

/**
 * The single deterministic, non-negative hash used everywhere deskhand derives
 * a stable value from a slug (ports in §7, the Redis DB index). Centralising it
 * guarantees "same branch → same everything" across services and platforms.
 */
final class Hash
{
    public static function of(string $value): int
    {
        // Mask the sign bit so the result is non-negative on every platform
        // (crc32 can return a negative int on 32-bit PHP).
        return crc32($value) & 0x7FFFFFFF;
    }
}
