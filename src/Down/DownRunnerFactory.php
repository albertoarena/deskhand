<?php

declare(strict_types=1);

namespace Deskhand\Down;

/**
 * Builds a fully-wired {@see DownRunner} for the directory `down` was invoked
 * from. The seam keeps the command thin and lets tests substitute a runner
 * built from fakes.
 */
interface DownRunnerFactory
{
    public function create(string $workingDirectory): DownRunner;
}
