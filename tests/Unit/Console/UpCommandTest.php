<?php

declare(strict_types=1);

use Deskhand\Console\Command\UpCommand;
use Deskhand\Core\Config\ConfigLoader;
use Deskhand\Core\Gitignore\GitignoreManager;
use Deskhand\Core\Planning\WorktreePlanner;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Tests\Fakes\FakeCapabilityDetector;
use Deskhand\Tests\Fakes\FakeDatabaseProvisioner;
use Deskhand\Tests\Fakes\FakeDatabaseProvisionerFactory;
use Deskhand\Tests\Fakes\FakeEnvMaterializer;
use Deskhand\Tests\Fakes\FakeGitRunner;
use Deskhand\Tests\Fakes\FakeProcessRunner;
use Deskhand\Tests\Fakes\FakeRegistry;
use Deskhand\Tests\Fakes\FakeStackProfile;
use Deskhand\Up\UpRunner;
use Deskhand\Up\UpRunnerFactory;
use Symfony\Component\Console\Tester\CommandTester;

function commandTester(object $t): CommandTester
{
    $factory = new class($t->runner) implements UpRunnerFactory
    {
        public function __construct(private UpRunner $runner) {}

        public function create(string $workingDirectory): UpRunner
        {
            return $this->runner;
        }
    };

    return new CommandTester(new UpCommand($factory));
}

beforeEach(function () {
    $this->repo = deskhandTempDir();

    $this->git = new FakeGitRunner;
    $this->git->root = $this->repo; // pin the repo root to the temp dir
    $this->profile = new FakeStackProfile;
    $this->registry = new FakeRegistry;

    $env = new FakeEnvMaterializer;
    $env->seed($this->repo.'/.env', ['APP_NAME' => 'Acme', 'DB_DATABASE' => 'acme']);
    $caps = new FakeCapabilityDetector;
    $config = ConfigLoader::fromArray([]);

    $this->runner = new UpRunner(
        $this->git,
        new FakeProcessRunner,
        $this->registry,
        $env,
        $caps,
        $this->profile,
        new FakeDatabaseProvisionerFactory(new FakeDatabaseProvisioner('sqlite')),
        new WorktreePlanner($config, new UrlResolver($config), $this->registry),
        new GitignoreManager,
        $config,
    );
});

afterEach(function () {
    deskhandRemoveDir($this->repo);
});

it('provisions and prints a summary on success', function () {
    $tester = commandTester($this);

    $exit = $tester->execute(['branch' => 'feature/billing']);

    expect($exit)->toBe(0);

    $display = $tester->getDisplay();
    expect($display)->toContain('deskhand up: feature-billing')
        ->and($display)->toContain('branch:   feature/billing')
        ->and($display)->toContain('verified (suite green)')
        ->and($this->registry->find('feature-billing'))->not->toBeNull();
});

it('rejects an unknown --db engine', function () {
    $tester = commandTester($this);

    $exit = $tester->execute(['branch' => 'feature/billing', '--db' => 'postgres']);

    expect($exit)->toBe(1)
        ->and($tester->getDisplay())->toContain("Unknown --db engine 'postgres'");
});

it('maps a verification failure to exit code 6', function () {
    $this->profile->verifyResult = false;
    $tester = commandTester($this);

    $exit = $tester->execute(['branch' => 'feature/billing']);

    expect($exit)->toBe(6)
        ->and($tester->getDisplay())->toContain('verification suite failed');
});

it('reports the shared database in the summary', function () {
    $tester = commandTester($this);

    $tester->execute(['branch' => 'feature/billing', '--shared-db' => true]);

    expect($tester->getDisplay())->toContain('shared');
});

it('reports skipped verification with --no-verify', function () {
    $this->profile->verifyResult = false; // would fail if it ran
    $tester = commandTester($this);

    $exit = $tester->execute(['branch' => 'feature/billing', '--no-verify' => true]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('verification skipped');
});
