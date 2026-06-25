<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * Resolves the {@see Registry} for the directory a read-only command was invoked
 * from. The seam keeps `list`/`status` thin and lets tests substitute a fake.
 */
interface RegistryLocator
{
    public function locate(string $workingDirectory): Registry;
}
