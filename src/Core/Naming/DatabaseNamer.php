<?php

declare(strict_types=1);

namespace Deskhand\Core\Naming;

use InvalidArgumentException;

/**
 * The canonical DB-naming scheme (§7). SQLite files live in the deskhand-
 * exclusive `database/deskhand/` directory, which namespaces them — so no
 * `_wt_` infix. MySQL databases share the server namespace, so the `_wt_`
 * ("worktree") infix is kept to disambiguate deskhand's DBs from the project's
 * real base database. The infix must never be dropped for MySQL.
 */
final class DatabaseNamer
{
    public const string ENGINE_SQLITE = 'sqlite';

    public const string ENGINE_MYSQL = 'mysql';

    private const string SQLITE_DIR = 'database/deskhand';

    public function main(string $engine, string $slug, string $base): string
    {
        return match ($engine) {
            self::ENGINE_SQLITE => self::SQLITE_DIR."/{$slug}.sqlite",
            self::ENGINE_MYSQL => "{$base}_wt_{$slug}",
            default => throw self::unknownEngine($engine),
        };
    }

    public function test(string $engine, string $slug, string $base, int $n): string
    {
        return match ($engine) {
            self::ENGINE_SQLITE => self::SQLITE_DIR."/{$slug}_test_{$n}.sqlite",
            self::ENGINE_MYSQL => "{$base}_wt_{$slug}_test_{$n}",
            default => throw self::unknownEngine($engine),
        };
    }

    private static function unknownEngine(string $engine): InvalidArgumentException
    {
        return new InvalidArgumentException("Unknown database engine '{$engine}'; expected 'sqlite' or 'mysql'.");
    }
}
