<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Capability\CapabilityDetector;

final class FakeCapabilityDetector implements CapabilityDetector
{
    public bool $composer = true;

    public bool $npm = true;

    public bool $yarn = true;

    public bool $mysqlClient = true;

    public bool $frontend = false;

    public bool $parallelTesting = true;

    public bool $storageLink = true;

    public ?string $packageManager = 'npm';

    public function hasComposer(): bool
    {
        return $this->composer;
    }

    public function hasNpm(): bool
    {
        return $this->npm;
    }

    public function hasYarn(): bool
    {
        return $this->yarn;
    }

    public function hasMysqlClient(): bool
    {
        return $this->mysqlClient;
    }

    public function hasFrontend(string $projectPath): bool
    {
        return $this->frontend;
    }

    public function detectPackageManager(string $projectPath): ?string
    {
        return $this->packageManager;
    }

    public function hasParallelTesting(string $projectPath): bool
    {
        return $this->parallelTesting;
    }

    public function needsStorageLink(string $projectPath): bool
    {
        return $this->storageLink;
    }
}
