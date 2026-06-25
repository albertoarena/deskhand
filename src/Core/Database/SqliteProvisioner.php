<?php

declare(strict_types=1);

namespace Deskhand\Core\Database;

use Deskhand\Exception\DatabaseProvisionException;
use PDO;
use PDOException;
use Throwable;

/**
 * SQLite database lifecycle (§4.1 step 8). `$name` is a file path (from the §7
 * naming rules), resolved relative to the worktree base directory unless it is
 * already absolute. SQLite files live in the deskhand-exclusive, gitignored
 * `database/deskhand/` directory.
 *
 * create() is idempotent: an existing file is left untouched so re-running `up`
 * never clobbers data deskhand already created. drop() tolerates a missing file
 * (safe teardown on partial state).
 */
final class SqliteProvisioner implements DatabaseProvisioner
{
    public function __construct(private readonly string $baseDirectory) {}

    public function engine(): string
    {
        return 'sqlite';
    }

    public function canConnect(): bool
    {
        return is_dir($this->baseDirectory) && is_writable($this->baseDirectory);
    }

    public function exists(string $name): bool
    {
        return is_file($this->resolve($name));
    }

    public function create(string $name): void
    {
        $path = $this->resolve($name);

        if (is_file($path)) {
            return;
        }

        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
            throw new DatabaseProvisionException("Unable to create the directory {$dir} for the SQLite database.");
        }

        try {
            // Opening a PDO handle creates the file and validates the driver.
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo = null;
        } catch (PDOException $e) {
            throw new DatabaseProvisionException("Unable to create the SQLite database at {$path}: {$e->getMessage()}", previous: $e);
        }

        if (! is_file($path)) {
            throw new DatabaseProvisionException("The SQLite database at {$path} was not created.");
        }
    }

    public function drop(string $name): void
    {
        $path = $this->resolve($name);

        if (! is_file($path)) {
            return;
        }

        try {
            if (! unlink($path)) {
                throw new DatabaseProvisionException("Unable to drop the SQLite database at {$path}.");
            }
        } catch (DatabaseProvisionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DatabaseProvisionException("Unable to drop the SQLite database at {$path}: {$e->getMessage()}", previous: $e);
        }
    }

    private function resolve(string $name): string
    {
        return str_starts_with($name, '/') ? $name : rtrim($this->baseDirectory, '/').'/'.$name;
    }
}
