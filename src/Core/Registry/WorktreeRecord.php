<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * The full per-worktree registry record (§5.1) — the single source of truth for
 * what deskhand created and is therefore allowed to destroy.
 *
 * @phpstan-type RecordArray array{
 *     slug: string,
 *     branch: string,
 *     path: string,
 *     created_at: string,
 *     db: array{engine: string, shared: bool, main: string, test_dbs?: list<string>},
 *     ports: array{serve: int, vite: int},
 *     redis: array{isolated: bool, prefix?: string|null, db_index?: int|null},
 *     url: string
 * }
 */
final class WorktreeRecord
{
    public function __construct(
        public readonly string $slug,
        public readonly string $branch,
        public readonly string $path,
        public readonly string $createdAt,
        public readonly DatabaseRecord $db,
        public readonly PortAllocation $ports,
        public readonly RedisAllocation $redis,
        public readonly string $url,
    ) {}

    /**
     * @return array{
     *     slug: string,
     *     branch: string,
     *     path: string,
     *     created_at: string,
     *     db: array{engine: string, shared: bool, main: string, test_dbs: list<string>},
     *     ports: array{serve: int, vite: int},
     *     redis: array{isolated: bool, prefix: string|null, db_index: int|null},
     *     url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'branch' => $this->branch,
            'path' => $this->path,
            'created_at' => $this->createdAt,
            'db' => $this->db->toArray(),
            'ports' => $this->ports->toArray(),
            'redis' => $this->redis->toArray(),
            'url' => $this->url,
        ];
    }

    /**
     * @param  RecordArray  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            branch: $data['branch'],
            path: $data['path'],
            createdAt: $data['created_at'],
            db: DatabaseRecord::fromArray($data['db']),
            ports: PortAllocation::fromArray($data['ports']),
            redis: RedisAllocation::fromArray($data['redis']),
            url: $data['url'],
        );
    }
}
