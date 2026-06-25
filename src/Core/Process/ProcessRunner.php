<?php

declare(strict_types=1);

namespace Deskhand\Core\Process;

/**
 * Runs an external command in a working directory with extra environment,
 * capturing the result. The only concrete implementation touches the real
 * process layer; everything else depends on this interface.
 */
interface ProcessRunner
{
    /**
     * @param  list<string>  $command  argv-style command, not a shell string
     * @param  array<string, string>  $env  extra env vars merged over the inherited environment
     */
    public function run(array $command, string $workingDirectory, array $env = [], ?float $timeout = null): ProcessResult;

    /**
     * Run a user-configured command string verbatim through a shell, so shell
     * features and `$DESKHAND_*` variable expansion work (§9). Used for the
     * configured migrate/seed/test commands and post-up hooks — never for
     * deskhand's own internal commands, which use {@see run()}.
     *
     * @param  array<string, string>  $env  extra env vars merged over the inherited environment
     */
    public function runShell(string $command, string $workingDirectory, array $env = [], ?float $timeout = null): ProcessResult;
}
