<?php

declare(strict_types=1);

namespace Deskhand\Core\Database;

/**
 * Builds the right {@see DatabaseProvisioner} for the engine chosen at runtime,
 * with the context each needs: the worktree path (SQLite file location) and the
 * base `.env` (MySQL connection parameters).
 */
interface DatabaseProvisionerFactory
{
    /**
     * @param  array<string, string>  $baseEnv
     */
    public function for(string $engine, string $worktreePath, array $baseEnv): DatabaseProvisioner;
}
