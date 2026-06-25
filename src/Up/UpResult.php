<?php

declare(strict_types=1);

namespace Deskhand\Up;

use Deskhand\Core\Registry\WorktreeRecord;

/**
 * The outcome of an `up` run, for the command layer to render its summary.
 */
final class UpResult
{
    /**
     * @param  list<string>  $gitignoreAdded
     */
    public function __construct(
        public readonly WorktreeRecord $record,
        public readonly array $gitignoreAdded,
        public readonly bool $sharedDb,
        public readonly bool $verified,
        public readonly bool $verifySkipped,
        public readonly bool $envauditSkipped,
        public readonly ?string $packageManager,
    ) {}
}
