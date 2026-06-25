<?php

declare(strict_types=1);

namespace Deskhand\Status;

/**
 * Builds a fully-wired {@see StatusRunner} for the working directory. The seam
 * keeps the command thin and lets tests substitute a runner built from fakes.
 */
interface StatusRunnerFactory
{
    public function create(string $workingDirectory): StatusRunner;
}
