<?php

declare(strict_types=1);

namespace Deskhand\Console\Command;

use Deskhand\Down\DownRunnerFactory;
use Deskhand\Exception\DeskhandException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * `deskhand down <branch|slug>` — tear down a worktree environment, removing
 * only what deskhand created (§4.2). Destructive, so it confirms first; with no
 * TTY it refuses unless `--force` is given. If there is no registry record it
 * does nothing — never inferring what to remove from the name.
 */
final class DownCommand extends Command
{
    public function __construct(private readonly DownRunnerFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('down')
            ->setDescription('Tear down a worktree environment, removing only what deskhand created')
            ->addArgument('target', InputArgument::REQUIRED, 'The branch or slug identifying the worktree')
            ->addOption('keep-branch', null, InputOption::VALUE_NONE, 'Remove the worktree but leave the git branch in place')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Proceed without interactive confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $target */
        $target = $input->getArgument('target');
        $workingDirectory = getcwd() ?: '.';

        try {
            $runner = $this->factory->create($workingDirectory);
            $record = $runner->find($target);

            if ($record === null) {
                $output->writeln("Nothing to tear down for '{$target}' — no deskhand record found.");

                return Command::SUCCESS;
            }

            if (! $input->getOption('force')) {
                if (! $input->isInteractive()) {
                    $output->writeln('<error>down needs confirmation but no interactive terminal is available. Re-run with --force to proceed.</error>');

                    return Command::FAILURE;
                }

                $question = new ConfirmationQuestion(
                    "Tear down '{$record->slug}' (branch {$record->branch})? [y/N] ",
                    false,
                );

                if (! (new QuestionHelper)->ask($input, $output, $question)) {
                    $output->writeln('Aborted.');

                    return Command::SUCCESS;
                }
            }

            $result = $runner->tearDown(
                $record,
                (bool) $input->getOption('keep-branch'),
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

        $output->writeln('');
        $output->writeln("<info>deskhand down: {$result->slug}</info>");

        foreach ($result->warnings as $warning) {
            $output->writeln("  <comment>warning: {$warning}</comment>");
        }

        return Command::SUCCESS;
    }
}
