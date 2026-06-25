<?php

declare(strict_types=1);

namespace Deskhand\Status;

/**
 * Tests whether a local TCP port is currently in use. Behind a seam so status
 * checks stay deterministic in tests.
 */
interface PortChecker
{
    public function isInUse(int $port): bool;
}
