<?php

declare(strict_types=1);

namespace Deskhand\Core\Database;

/**
 * Lifecycle for an isolated database. One implementation per engine
 * (SqliteProvisioner, MysqlProvisioner), selected at runtime.
 *
 * `$name` is the engine-appropriate identifier produced by the naming rules
 * (§7): a file path for SQLite, a database name for MySQL.
 */
interface DatabaseProvisioner
{
    /** 'sqlite' | 'mysql' */
    public function engine(): string;

    /** Whether the engine is reachable (server up for MySQL, writable dir for SQLite). */
    public function canConnect(): bool;

    public function exists(string $name): bool;

    public function create(string $name): void;

    public function drop(string $name): void;
}
