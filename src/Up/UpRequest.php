<?php

declare(strict_types=1);

namespace Deskhand\Up;

/**
 * The parsed inputs for an `up` run: the branch, the directory it was invoked
 * from, the chosen engine and the §4.1 flags. `createdAt` is injected so the
 * resulting record's timestamp stays explicit and testable.
 */
final class UpRequest
{
    public function __construct(
        public readonly string $branch,
        public readonly string $workingDirectory,
        public readonly string $createdAt,
        public readonly string $engine = 'sqlite',
        public readonly bool $shared = false,
        public readonly ?string $pathFlag = null,
        public readonly ?string $urlFlag = null,
        public readonly bool $skipEnvaudit = false,
        public readonly bool $skipRedisIsolation = false,
        public readonly bool $skipVerify = false,
    ) {}
}
