<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * Conditional Redis namespacing for a worktree: a key prefix (primary) and a
 * best-effort logical DB index (§7). Both null when isolation is inactive.
 */
final class RedisAllocation
{
    public function __construct(
        public readonly bool $isolated,
        public readonly ?string $prefix = null,
        public readonly ?int $dbIndex = null,
    ) {}

    /**
     * @return array{isolated: bool, prefix: string|null, db_index: int|null}
     */
    public function toArray(): array
    {
        return [
            'isolated' => $this->isolated,
            'prefix' => $this->prefix,
            'db_index' => $this->dbIndex,
        ];
    }

    /**
     * @param  array{isolated: bool, prefix?: string|null, db_index?: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isolated: $data['isolated'],
            prefix: $data['prefix'] ?? null,
            dbIndex: $data['db_index'] ?? null,
        );
    }
}
