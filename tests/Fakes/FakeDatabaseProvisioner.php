<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Database\DatabaseProvisioner;

final class FakeDatabaseProvisioner implements DatabaseProvisioner
{
    public bool $connectable = true;

    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $dropped = [];

    /** @var list<string> */
    private array $existing = [];

    public function __construct(private readonly string $engine = 'sqlite') {}

    public function engine(): string
    {
        return $this->engine;
    }

    public function canConnect(): bool
    {
        return $this->connectable;
    }

    public function markExisting(string $name): void
    {
        if (! in_array($name, $this->existing, true)) {
            $this->existing[] = $name;
        }
    }

    public function exists(string $name): bool
    {
        return in_array($name, $this->existing, true);
    }

    public function create(string $name): void
    {
        $this->created[] = $name;

        if (! $this->exists($name)) {
            $this->existing[] = $name;
        }
    }

    public function drop(string $name): void
    {
        $this->dropped[] = $name;
        $this->existing = array_values(array_filter(
            $this->existing,
            fn (string $existing): bool => $existing !== $name,
        ));
    }
}
