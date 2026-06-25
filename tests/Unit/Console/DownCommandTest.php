<?php

declare(strict_types=1);

use Deskhand\Console\Command\DownCommand;
use Deskhand\Down\DownRunner;
use Deskhand\Down\DownRunnerFactory;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakeGitRunner;
use Deskhand\Tests\Fakes\FakeRegistry;
use Symfony\Component\Console\Tester\CommandTester;

function downCommandTester(object $t): CommandTester
{
    $factory = new class($t->runner) implements DownRunnerFactory
    {
        public function __construct(private DownRunner $runner) {}

        public function create(string $workingDirectory): DownRunner
        {
            return $this->runner;
        }
    };

    return new CommandTester(new DownCommand($factory));
}

beforeEach(function () {
    $this->repo = deskhandTempDir();
    $this->git = new FakeGitRunner;
    $this->git->root = $this->repo;
    $this->registry = new FakeRegistry;
    $this->provisioner = new FakeDatabaseProvisioner('sqlite');

    $this->runner = new DownRunner(
        $this->git,
        $this->registry,
        new FakeDatabaseProvisionerFactory($this->provisioner),
        new FakeEnvMaterializer,
        $this->repo,
    );
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('never drops anything when there is no registry record (cardinal safety invariant)', function () {
    // registry is empty
    $tester = downCommandTester($this);

    $exit = $tester->execute(['target' => 'feature/billing', '--force' => true]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('Nothing to tear down')
        ->and($this->provisioner->dropped)->toBe([]);
});

it('refuses without --force when there is no interactive terminal', function () {
    $this->registry->save(sampleRecord());
    $tester = downCommandTester($this);

    $exit = $tester->execute(['target' => 'feature/billing'], ['interactive' => false]);

    expect($exit)->toBe(1)
        ->and($tester->getDisplay())->toContain('Re-run with --force')
        ->and($this->provisioner->dropped)->toBe([])
        ->and($this->registry->find('feature-billing'))->not->toBeNull(); // untouched
});

it('tears down with --force', function () {
    $this->registry->save(sampleRecord());
    $tester = downCommandTester($this);

    $exit = $tester->execute(['target' => 'feature/billing', '--force' => true]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('deskhand down: feature-billing')
        ->and($this->provisioner->dropped)->toBe(['database/deskhand/feature-billing.sqlite'])
        ->and($this->registry->all())->toBe([]);
});

it('keeps the branch with --keep-branch', function () {
    $this->registry->save(sampleRecord());
    $this->git->registerBranch('feature/billing');
    $tester = downCommandTester($this);

    $tester->execute(['target' => 'feature/billing', '--force' => true, '--keep-branch' => true]);

    expect($this->git->branchExists('feature/billing', $this->repo))->toBeTrue();
});
