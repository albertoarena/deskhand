<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

/**
 * The deterministic, slug-derived ports assigned to a worktree.
 */
final class PortAllocation
{
    public function __construct(
        public readonly int $serve,
        public readonly int $vite,
    ) {}

    /**
     * @return array{serve: int, vite: int}
     */
    public function toArray(): array
    {
        return ['serve' => $this->serve, 'vite' => $this->vite];
    }

    /**
     * @param  array{serve: int, vite: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(serve: $data['serve'], vite: $data['vite']);
    }
}
