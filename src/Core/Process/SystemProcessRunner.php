<?php

declare(strict_types=1);

namespace Deskhand\Core\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The one place deskhand touches the real process layer (safety invariant #8).
 * Wraps symfony/process: argv-style commands via run() for deskhand's own
 * internal commands (never a shell string), and verbatim shell strings via
 * runShell() for user-configured commands and hooks (§9). Both honour a working
 * directory, env vars merged over the inherited environment, and an optional
 * timeout (null disables it).
 *
 * A non-zero exit — including a missing binary — is returned as a failed
 * {@see ProcessResult}, not thrown, so callers like CapabilityDetector can probe
 * with `which`. A timeout is surfaced as a failed result with exit code 124.
 */
final class SystemProcessRunner implements ProcessRunner
{
    private const int TIMEOUT_EXIT_CODE = 124;

    public function run(array $command, string $workingDirectory, array $env = [], ?float $timeout = null): ProcessResult
    {
        return $this->execute(new Process($command, $workingDirectory, $env, null, $timeout));
    }

    public function runShell(string $command, string $workingDirectory, array $env = [], ?float $timeout = null): ProcessResult
    {
        return $this->execute(Process::fromShellCommandline($command, $workingDirectory, $env, null, $timeout));
    }

    private function execute(Process $process): ProcessResult
    {
        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return new ProcessResult(
                self::TIMEOUT_EXIT_CODE,
                $process->getOutput(),
                trim($process->getErrorOutput()."\n".$e->getMessage()),
            );
        }

        return new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
