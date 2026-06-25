<?php

declare(strict_types=1);

namespace Deskhand\Core\Config;

/**
 * Immutable, fully-resolved deskhand configuration (§9). Every key carries a
 * concrete value: the loader has already applied defaults, so consumers never
 * deal with "missing" config. Tri-state keys (frontendInstall, redisIsolation)
 * are normalised to the keywords 'auto' | 'true' | 'false'.
 */
final class Config
{
    /**
     * @param  list<string>  $postUpHooks
     */
    public function __construct(
        public readonly ?string $dbConnection,
        public readonly string $servePortRange,
        public readonly string $vitePortRange,
        public readonly string $frontendInstall,
        public readonly string $jsPackageManager,
        public readonly bool $seed,
        public readonly string $urlStrategy,
        public readonly ?string $urlTemplate,
        public readonly string $urlDomain,
        public readonly string $migrateCommand,
        public readonly string $seedCommand,
        public readonly string $testCommand,
        public readonly array $postUpHooks,
        public readonly string $redisIsolation,
    ) {}
}
