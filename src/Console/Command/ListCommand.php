<?php

declare(strict_types=1);

namespace Deskhand\Console\Command;

use Deskhand\Core\Registry\RegistryLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `deskhand list` — list all deskhand-managed worktrees from the registry
 * (§4.3). Tabular by default, `--json` for machine-readable output.
 */
final class ListCommand extends Command
{
    public function __construct(private readonly RegistryLocator $locator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('List deskhand-managed worktrees')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $records = $this->locator->locate(getcwd() ?: '.')->all();

        if ($input->getOption('json')) {
            $payload = array_map(fn ($record): array => $record->toArray(), $records);
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

            return Command::SUCCESS;
        }

        if ($records === []) {
            $output->writeln('No deskhand worktrees.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Slug', 'Branch', 'Path', 'Database', 'Ports', 'URL', 'Created']);

        foreach ($records as $record) {
            $table->addRow([
                $record->slug,
                $record->branch,
                $record->path,
                $record->db->engine.':'.$record->db->main,
                $record->ports->serve.'/'.$record->ports->vite,
                $record->url,
                $record->createdAt,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
