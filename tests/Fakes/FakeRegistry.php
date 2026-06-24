<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\WorktreeRecord;

final class FakeRegistry implements Registry
{
    /** @var array<string, WorktreeRecord> keyed by slug */
    private array $records = [];

    public function all(): array
    {
        return array_values($this->records);
    }

    public function find(string $slugOrBranch): ?WorktreeRecord
    {
        foreach ($this->records as $record) {
            if ($record->slug === $slugOrBranch || $record->branch === $slugOrBranch) {
                return $record;
            }
        }

        return null;
    }

    public function save(WorktreeRecord $record): void
    {
        $this->records[$record->slug] = $record;
    }

    public function remove(string $slug): void
    {
        unset($this->records[$slug]);
    }
}
