<?php

declare(strict_types=1);

namespace Deskhand\Console\Command;

use Deskhand\Exception\DeskhandException;
use Deskhand\Up\UpRequest;
use Deskhand\Up\UpResult;
use Deskhand\Up\UpRunnerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `deskhand up <branch>` — provision a fully runnable, verified, isolated
 * environment (§4.1). Thin wrapper: it parses input, delegates to the
 * {@see UpRunner}, renders the summary, and maps typed exceptions to exit codes.
 */
final class UpCommand extends Command
{
    private const array ENGINES = ['sqlite', 'mysql'];

    public function __construct(private readonly UpRunnerFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('up')
            ->setDescription('Provision an isolated, test-passing environment for a branch')
            ->addArgument('branch', InputArgument::REQUIRED, 'The git branch (attached if it exists, created if not)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Worktree location (default: .claude/worktrees/<slug>)')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'Database engine: sqlite or mysql', 'sqlite')
            ->addOption('shared-db', null, InputOption::VALUE_NONE, 'Use the base project database read-only (skips DB create, migrate and seed)')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Override the reported worktree URL (supports {slug}/{port})')
            ->addOption('no-envaudit', null, InputOption::VALUE_NONE, 'Skip the envaudit gate')
            ->addOption('no-redis-isolation', null, InputOption::VALUE_NONE, 'Skip Redis prefix/index injection')
            ->addOption('no-verify', null, InputOption::VALUE_NONE, 'Skip the verification suite (provision only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $engine */
        $engine = $input->getOption('db');

        if (! in_array($engine, self::ENGINES, true)) {
            $output->writeln("<error>Unknown --db engine '{$engine}'; expected sqlite or mysql.</error>");

            return Command::FAILURE;
        }

        $workingDirectory = getcwd() ?: '.';

        /** @var string $branch */
        $branch = $input->getArgument('branch');

        $request = new UpRequest(
            branch: $branch,
            workingDirectory: $workingDirectory,
            createdAt: gmdate('Y-m-d\TH:i:s\Z'),
            engine: $engine,
            shared: (bool) $input->getOption('shared-db'),
            pathFlag: $this->stringOption($input, 'path'),
            urlFlag: $this->stringOption($input, 'url'),
            skipEnvaudit: (bool) $input->getOption('no-envaudit'),
            skipRedisIsolation: (bool) $input->getOption('no-redis-isolation'),
            skipVerify: (bool) $input->getOption('no-verify'),
        );

        try {
            $result = $this->factory->create($workingDirectory)->run(
                $request,
                function (string $message) use ($output): void {
                    $output->writeln("  {$message}");
                },
            );
        } catch (DeskhandException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            if ($output->isVerbose()) {
                throw $e;
            }

            return $e->exitCode();
        }

        $this->renderSummary($output, $result);

        return Command::SUCCESS;
    }

    private function renderSummary(OutputInterface $output, UpResult $result): void
    {
        $record = $result->record;
        $health = match (true) {
            $result->verifySkipped => 'verification skipped',
            $result->verified => 'verified (suite green)',
            default => 'provisioned',
        };

        $output->writeln('');
        $output->writeln("<info>deskhand up: {$record->slug}</info>");
        $output->writeln("  branch:   {$record->branch}");
        $output->writeln("  path:     {$record->path}");
        $output->writeln('  database: '.$record->db->main.' ('.$record->db->engine.($result->sharedDb ? ', shared' : '').')');
        $output->writeln("  ports:    serve {$record->ports->serve}, vite {$record->ports->vite}");
        $output->writeln("  url:      {$record->url}");
        $output->writeln("  health:   {$health}");
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : null;
    }
}
