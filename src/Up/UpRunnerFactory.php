<?php

declare(strict_types=1);

namespace Deskhand\Up;

/**
 * Builds a fully-wired {@see UpRunner} for the directory `up` was invoked from.
 * The seam lets the command stay thin and lets tests substitute a runner built
 * from fakes.
 */
interface UpRunnerFactory
{
    public function create(string $workingDirectory): UpRunner;
}
