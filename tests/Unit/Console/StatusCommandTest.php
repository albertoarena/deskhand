<?php

declare(strict_types=1);

use Deskhand\Console\Command\StatusCommand;
use Deskhand\Status\StatusRunner;
use Deskhand\Status\StatusRunnerFactory;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakePortChecker;
use Deskhand\Tests\Fakes\FakeRegistry;
use Symfony\Component\Console\Tester\CommandTester;

function statusCommandTester(object $t): CommandTester
{
    $runner = new StatusRunner(
        $t->registry,
        new FakeDatabaseProvisionerFactory($t->provisioner),
        new FakeEnvMaterializer,
        $t->ports,
        $t->repo,
    );

    $factory = new class($runner) implements StatusRunnerFactory
    {
        public function __construct(private StatusRunner $runner) {}

        public function create(string $workingDirectory): StatusRunner
        {
            return $this->runner;
        }
    };

    return new CommandTester(new StatusCommand($factory));
}

beforeEach(function () {
    $this->repo = deskhandTempDir();
    $this->registry = new FakeRegistry;
    $this->provisioner = new FakeDatabaseProvisioner('sqlite');
    $this->ports = new FakePortChecker;
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('shows a problem for a worktree with a missing directory', function () {
    $this->registry->save(sampleRecord());

    $tester = statusCommandTester($this);
    $tester->execute([]);

    $display = $tester->getDisplay();
    expect($display)->toContain('feature-billing')
        ->and($display)->toContain('worktree directory missing');
});

it('reports nothing for an unknown target', function () {
    $tester = statusCommandTester($this);

    $tester->execute(['target' => 'nope']);

    expect($tester->getDisplay())->toContain("No deskhand worktree found for 'nope'");
});

it('outputs JSON with --json', function () {
    $this->registry->save(sampleRecord());

    $tester = statusCommandTester($this);
    $tester->execute(['--json' => true]);

    $decoded = json_decode($tester->getDisplay(), true);
    expect($decoded)->toBeArray()
        ->and($decoded[0]['slug'])->toBe('feature-billing')
        ->and($decoded[0]['healthy'])->toBeFalse();
});
