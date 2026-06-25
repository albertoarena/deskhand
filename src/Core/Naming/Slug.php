<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

/**
 * Derives a filesystem- and DB-safe slug from a branch name (§7).
 *
 * The slug is the join key across the worktree path, DB name(s), APP_NAME tag
 * and registry record: lowercase, with every run of non-alphanumeric characters
 * collapsed to a single `-` and the edges trimmed.
 */
final class Slug
{
    public static function fromBranch(string $branch): string
    {
        $slug = strtolower($branch);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
