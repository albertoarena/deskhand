<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Status\PortChecker;

final class FakePortChecker implements PortChecker
{
    /** @var list<int> */
    public array $inUse = [];

    public function isInUse(int $port): bool
    {
        return in_array($port, $this->inUse, true);
    }
}
