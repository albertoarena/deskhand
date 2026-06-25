<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

/**
 * Builds the APP_NAME tag `<base> [<slug>]` so environments are distinguishable
 * in logs, mail and the browser (§7). `<base>` is the base `.env`'s APP_NAME;
 * if that is absent or empty it falls back to the project root directory name.
 * Never emits a leading space or an empty name.
 */
final class AppNameTag
{
    public static function make(?string $baseAppName, string $slug, string $projectDirName): string
    {
        $base = trim((string) $baseAppName);

        if ($base === '') {
            $base = $projectDirName;
        }

        return "{$base} [{$slug}]";
    }
}
