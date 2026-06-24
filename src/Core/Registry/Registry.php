<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * The persisted record of what deskhand created. The concrete implementation
 * is the gitignored JSON file (§5); all access goes through this interface.
 */
interface Registry
{
    /** @return list<WorktreeRecord> */
    public function all(): array;

    /** Find a record by its slug or its branch; null if none. */
    public function find(string $slugOrBranch): ?WorktreeRecord;

    /** Insert or update by slug — never duplicates an entry. */
    public function save(WorktreeRecord $record): void;

    public function remove(string $slug): void;
}
