<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * The database(s) deskhand created for a worktree — the basis for safe drop on
 * teardown. `main`/`testDbs` hold file paths for SQLite, database names for MySQL.
 */
final class DatabaseRecord
{
    /**
     * @param  list<string>  $testDbs
     */
    public function __construct(
        public readonly string $engine,
        public readonly bool $shared,
        public readonly string $main,
        public readonly array $testDbs = [],
    ) {}

    /**
     * @return array{engine: string, shared: bool, main: string, test_dbs: list<string>}
     */
    public function toArray(): array
    {
        return [
            'engine' => $this->engine,
            'shared' => $this->shared,
            'main' => $this->main,
            'test_dbs' => $this->testDbs,
        ];
    }

    /**
     * @param  array{engine: string, shared: bool, main: string, test_dbs?: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            engine: $data['engine'],
            shared: $data['shared'],
            main: $data['main'],
            testDbs: $data['test_dbs'] ?? [],
        );
    }
}
