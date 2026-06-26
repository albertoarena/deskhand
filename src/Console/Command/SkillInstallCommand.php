<?php

declare(strict_types=1);

namespace Deskhand\Console\Command;

use Deskhand\Exception\DeskhandException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `deskhand skill:install` — copy the bundled Claude Code skill into a place
 * Claude Code discovers it: the current project's `.claude/skills/deskhand/`
 * by default, or `~/.claude/skills/deskhand/` with `--global`. This makes the
 * versioned `skill/SKILL.md` usable without manual copying.
 */
final class SkillInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('skill:install')
            ->setDescription('Install the deskhand Claude Code skill into .claude/skills')
            ->addOption('global', null, InputOption::VALUE_NONE, 'Install for the current user (~/.claude/skills) instead of the project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = dirname(__DIR__, 3).'/skill/SKILL.md';

        if (! is_file($source)) {
            $output->writeln('<error>The bundled skill file could not be found.</error>');

            return Command::FAILURE;
        }

        $base = $input->getOption('global')
            ? $this->homeDirectory().'/.claude/skills'
            : (getcwd() ?: '.').'/.claude/skills';

        $directory = $base.'/deskhand';

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            $output->writeln("<error>Unable to create {$directory}.</error>");

            return Command::FAILURE;
        }

        $target = $directory.'/SKILL.md';
        $existed = is_file($target);

        if (! copy($source, $target)) {
            $output->writeln("<error>Unable to write {$target}.</error>");

            return Command::FAILURE;
        }

        $output->writeln(($existed ? 'Updated' : 'Installed')." the deskhand skill at {$target}");
        $output->writeln('Claude Code will discover it as the "deskhand" skill.');

        return Command::SUCCESS;
    }

    private function homeDirectory(): string
    {
        $home = getenv('HOME');

        if ($home === false || $home === '') {
            throw new DeskhandException('Cannot determine the home directory for a --global install.');
        }

        return $home;
    }
}
