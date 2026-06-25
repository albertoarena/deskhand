<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Database\DatabaseProvisioner;
use Deskhand\Core\Database\DatabaseProvisionerFactory;

final class FakeDatabaseProvisionerFactory implements DatabaseProvisionerFactory
{
    /** @var list<array{engine: string, worktreePath: string}> */
    public array $calls = [];

    public function __construct(public readonly FakeDatabaseProvisioner $provisioner = new FakeDatabaseProvisioner) {}

    public function for(string $engine, string $worktreePath, array $baseEnv): DatabaseProvisioner
    {
        $this->calls[] = ['engine' => $engine, 'worktreePath' => $worktreePath];

        return $this->provisioner;
    }
}
