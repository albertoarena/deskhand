<?php

declare(strict_types=1);

namespace Deskhand\Core\Registry;

use Deskhand\Exception\RegistryException;
use JsonException;
use Throwable;

/**
 * The concrete registry (§5): a single JSON file at the fixed per-repo path
 * `.claude/deskhand/registry.json`, holding a list of {@see WorktreeRecord}s.
 *
 * This file is the single source of truth for what deskhand created and is
 * therefore allowed to destroy, so reads fail loudly on corruption rather than
 * silently pretending the registry is empty (which could orphan databases).
 * A missing or empty file is the normal first-run state and yields no records.
 *
 * @phpstan-import-type RecordArray from WorktreeRecord
 */
final class JsonRegistry implements Registry
{
    public const string RELATIVE_PATH = '.claude/deskhand/registry.json';

    public function __construct(private readonly string $path) {}

    public static function pathFor(string $repoRoot): string
    {
        return rtrim($repoRoot, '/').'/'.self::RELATIVE_PATH;
    }

    public function all(): array
    {
        return $this->load();
    }

    public function find(string $slugOrBranch): ?WorktreeRecord
    {
        foreach ($this->load() as $record) {
            if ($record->slug === $slugOrBranch || $record->branch === $slugOrBranch) {
                return $record;
            }
        }

        return null;
    }

    public function save(WorktreeRecord $record): void
    {
        $records = $this->load();
        $replaced = false;

        foreach ($records as $i => $existing) {
            if ($existing->slug === $record->slug) {
                $records[$i] = $record;
                $replaced = true;
                break;
            }
        }

        if (! $replaced) {
            $records[] = $record;
        }

        $this->write($records);
    }

    public function remove(string $slug): void
    {
        $before = $this->load();
        $after = array_values(array_filter($before, fn (WorktreeRecord $r): bool => $r->slug !== $slug));

        if (count($after) !== count($before)) {
            $this->write($after);
        }
    }

    /**
     * @return list<WorktreeRecord>
     */
    private function load(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RegistryException("Unable to read the deskhand registry at {$this->path}.");
        }

        if (trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new RegistryException("The deskhand registry at {$this->path} is not a JSON array.");
            }

            $records = [];

            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    throw new RegistryException("The deskhand registry at {$this->path} contains a malformed record.");
                }

                /** @var RecordArray $row */
                $records[] = WorktreeRecord::fromArray($row);
            }

            return $records;
        } catch (RegistryException $e) {
            throw $e;
        } catch (JsonException $e) {
            throw new RegistryException("The deskhand registry at {$this->path} is corrupt: {$e->getMessage()}", previous: $e);
        } catch (Throwable $e) {
            throw new RegistryException("The deskhand registry at {$this->path} has an unexpected shape: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @param  list<WorktreeRecord>  $records
     */
    private function write(array $records): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
            throw new RegistryException("Unable to create the deskhand registry directory at {$dir}.");
        }

        $payload = array_map(fn (WorktreeRecord $r): array => $r->toArray(), $records);

        try {
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RegistryException("Unable to encode the deskhand registry: {$e->getMessage()}", previous: $e);
        }

        // Write to a temporary file in the same directory, then atomically
        // rename, so a crash mid-write never leaves a half-written registry.
        $tmp = tempnam($dir, 'registry-');

        if ($tmp === false) {
            throw new RegistryException("Unable to create a temporary file for the deskhand registry in {$dir}.");
        }

        if (file_put_contents($tmp, $json.PHP_EOL) === false || ! rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RegistryException("Unable to write the deskhand registry at {$this->path}.");
        }
    }
}
