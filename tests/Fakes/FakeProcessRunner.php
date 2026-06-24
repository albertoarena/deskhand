<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Process\ProcessResult;
use Deskhand\Core\Process\ProcessRunner;

final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<ProcessResult> */
    private array $queue = [];

    /** @var list<array{command: list<string>, cwd: string, env: array<string, string>, timeout: float|null}> */
    public array $calls = [];

    public function queue(ProcessResult $result): void
    {
        $this->queue[] = $result;
    }

    public function run(array $command, string $workingDirectory, array $env = [], ?float $timeout = null): ProcessResult
    {
        $this->calls[] = [
            'command' => $command,
            'cwd' => $workingDirectory,
            'env' => $env,
            'timeout' => $timeout,
        ];

        return array_shift($this->queue) ?? new ProcessResult(0);
    }
}
