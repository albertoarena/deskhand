<?php

declare(strict_types=1);

namespace Deskhand\Console\Command;

use Deskhand\Status\StatusRunnerFactory;
use Deskhand\Status\WorktreeHealth;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `deskhand status [<branch|slug>]` — health check (§4.4). With no argument it
 * summarizes every managed worktree and flags problems; with an argument it
 * reports a single worktree. `--json` for machine-readable output.
 */
final class StatusCommand extends Command
{
    public function __construct(private readonly StatusRunnerFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Health check for deskhand-managed worktrees')
            ->addArgument('target', InputArgument::OPTIONAL, 'A branch or slug to inspect (default: all)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runner = $this->factory->create(getcwd() ?: '.');
        $target = $input->getArgument('target');

        if (is_string($target)) {
            $health = $runner->one($target);

            if ($health === null) {
                $output->writeln("No deskhand worktree found for '{$target}'.");

                return Command::SUCCESS;
            }

            $healths = [$health];
        } else {
            $healths = $runner->all();
        }

        if ($input->getOption('json')) {
            $payload = array_map(fn (WorktreeHealth $h): array => $h->toArray(), $healths);
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

            return Command::SUCCESS;
        }

        if ($healths === []) {
            $output->writeln('No deskhand worktrees.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Slug', 'Worktree', 'Env', 'Database', 'Serve', 'Vite', 'Health']);

        foreach ($healths as $health) {
            $table->addRow([
                $health->record->slug,
                $health->worktreeExists ? 'ok' : 'missing',
                $health->envExists ? 'ok' : 'missing',
                $health->databaseReachable ? 'ok' : 'unreachable',
                $health->servePortInUse ? 'in use' : 'free',
                $health->vitePortInUse ? 'in use' : 'free',
                $health->healthy() ? 'healthy' : implode('; ', $health->problems),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
