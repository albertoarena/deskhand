<?php

declare(strict_types=1);

namespace Deskhand\Down;

/**
 * The outcome of a teardown: what was dropped, whether the branch was removed,
 * and any non-fatal warnings collected while tearing down best-effort.
 */
final class DownResult
{
    /**
     * @param  list<string>  $droppedDatabases
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $slug,
        public readonly bool $branchRemoved,
        public readonly array $droppedDatabases,
        public readonly array $warnings,
    ) {}
}
