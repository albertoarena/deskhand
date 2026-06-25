<?php

declare(strict_types=1);

namespace Deskhand\Core\Database;

use Deskhand\Exception\DatabaseProvisionException;
use PDO;
use PDOException;
use Throwable;

/**
 * MySQL database lifecycle (§4.1 step 8). `$name` is a database name (from the
 * §7 naming rules, e.g. `<base>_wt_<slug>`). The connection is lazy so canConnect()
 * can report reachability without the constructor throwing.
 *
 * create() uses CREATE DATABASE IF NOT EXISTS and drop() uses DROP DATABASE IF
 * EXISTS, so re-running `up` never clobbers existing data and teardown is safe on
 * partial state. Names are validated and backtick-quoted defensively; this class
 * executes drops but never decides what to drop — that is the registry's job.
 */
final class MysqlProvisioner implements DatabaseProvisioner
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function engine(): string
    {
        return 'mysql';
    }

    public function canConnect(): bool
    {
        try {
            $this->connection();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function exists(string $name): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
        );
        $statement->execute([$name]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $name): void
    {
        $this->guardName($name);

        try {
            $this->connection()->exec('CREATE DATABASE IF NOT EXISTS '.$this->quoteIdentifier($name));
        } catch (PDOException $e) {
            throw new DatabaseProvisionException("Unable to create the MySQL database '{$name}': {$e->getMessage()}", previous: $e);
        }
    }

    public function drop(string $name): void
    {
        $this->guardName($name);

        try {
            $this->connection()->exec('DROP DATABASE IF EXISTS '.$this->quoteIdentifier($name));
        } catch (PDOException $e) {
            throw new DatabaseProvisionException("Unable to drop the MySQL database '{$name}': {$e->getMessage()}", previous: $e);
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d', $this->host, $this->port);
            $this->pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        return $this->pdo;
    }

    private function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function guardName(string $name): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new DatabaseProvisionException("Refusing to operate on unsafe database name '{$name}'.");
        }
    }
}
