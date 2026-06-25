<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Profile\StackProfile;

final class FakeStackProfile implements StackProfile
{
    public bool $verifyResult = true;

    /** @var array<string, string> */
    public array $overrides = [];

    /** @var array<string, string> */
    public array $testingOverrides = [];

    /** @var list<string> database names passed to migrate() */
    public array $migrated = [];

    /** @var array<string, string> env passed to the last migrate() */
    public array $migrateEnv = [];

    /** @var array<string, string> env passed to the last verify() */
    public array $verifyEnv = [];

    public bool $appKeyGenerated = false;

    public bool $storageProvisioned = false;

    public bool $seeded = false;

    public function name(): string
    {
        return 'fake';
    }

    public function envOverrides(WorktreeRecord $record, array $baseEnv, string $projectName): array
    {
        return $this->overrides;
    }

    public function testingEnvOverrides(): array
    {
        return $this->testingOverrides;
    }

    public function generateAppKey(string $worktreePath): void
    {
        $this->appKeyGenerated = true;
    }

    public function provisionStorage(string $worktreePath): void
    {
        $this->storageProvisioned = true;
    }

    public function migrate(string $worktreePath, string $databaseName, array $env = []): void
    {
        $this->migrated[] = $databaseName;
        $this->migrateEnv = $env;
    }

    public function seed(string $worktreePath, array $env = []): void
    {
        $this->seeded = true;
    }

    public function verify(string $worktreePath, array $env = []): bool
    {
        $this->verifyEnv = $env;

        return $this->verifyResult;
    }
}
