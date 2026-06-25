<?php

declare(strict_types=1);

namespace Deskhand\Core\Database;

use Deskhand\Core\Naming\DatabaseNamer;

/**
 * Default factory: SQLite files live under the worktree; MySQL connection
 * parameters come from the project's base `.env`.
 */
final class DefaultDatabaseProvisionerFactory implements DatabaseProvisionerFactory
{
    public function for(string $engine, string $worktreePath, array $baseEnv): DatabaseProvisioner
    {
        return match ($engine) {
            DatabaseNamer::ENGINE_MYSQL => new MysqlProvisioner(
                $baseEnv['DB_HOST'] ?? '127.0.0.1',
                (int) ($baseEnv['DB_PORT'] ?? '3306'),
                $baseEnv['DB_USERNAME'] ?? 'root',
                $baseEnv['DB_PASSWORD'] ?? '',
            ),
            default => new SqliteProvisioner($worktreePath),
        };
    }
}
