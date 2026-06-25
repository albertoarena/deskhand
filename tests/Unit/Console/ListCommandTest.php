<?php

declare(strict_types=1);

use Deskhand\Console\Command\ListCommand;
use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\RegistryLocator;
use Deskhand\Tests\Fakes\FakeRegistry;
use Symfony\Component\Console\Tester\CommandTester;

function listCommandTester(Registry $registry): CommandTester
{
    $locator = new class($registry) implements RegistryLocator
    {
        public function __construct(private Registry $registry) {}

        public function locate(string $workingDirectory): Registry
        {
            return $this->registry;
        }
    };

    return new CommandTester(new ListCommand($locator));
}

it('reports when there are no worktrees', function () {
    $tester = listCommandTester(new FakeRegistry);

    $tester->execute([]);

    expect($tester->getDisplay())->toContain('No deskhand worktrees.');
});

it('lists worktrees in a table', function () {
    $registry = new FakeRegistry;
    $registry->save(sampleRecord());

    $tester = listCommandTester($registry);
    $tester->execute([]);

    $display = $tester->getDisplay();
    expect($display)->toContain('feature-billing')
        ->and($display)->toContain('feature/billing')
        ->and($display)->toContain('sqlite:database/deskhand/feature-billing.sqlite');
});

it('outputs JSON with --json', function () {
    $registry = new FakeRegistry;
    $registry->save(sampleRecord());

    $tester = listCommandTester($registry);
    $tester->execute(['--json' => true]);

    $decoded = json_decode($tester->getDisplay(), true);
    expect($decoded)->toBeArray()
        ->and($decoded[0]['slug'])->toBe('feature-billing')
        ->and($decoded[0]['db']['engine'])->toBe('sqlite');
});
