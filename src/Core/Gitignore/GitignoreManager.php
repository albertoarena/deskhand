<?php

declare(strict_types=1);

namespace Deskhand\Core\Gitignore;

use Deskhand\Exception\DeskhandException;

/**
 * Idempotently maintains deskhand's managed block in the base repo's
 * `.gitignore` (§5.2), so deskhand's artifacts are never committed. Scoped to
 * specific subpaths — never ignores all of `.claude/`. Only missing lines are
 * appended; existing unrelated entries are never reordered or removed. Returns
 * the lines it added so `up` can report them.
 */
final class GitignoreManager
{
    public const string MARKER = '# deskhand (managed)';

    /** @var list<string> */
    private const array MANAGED_PATHS = [
        '.claude/worktrees/',
        '.claude/deskhand/',
        'database/deskhand/',
    ];

    /**
     * @return list<string> the managed paths that were added (empty if none)
     */
    public function ensure(string $repoRoot): array
    {
        $path = $repoRoot.'/.gitignore';
        $contents = is_file($path) ? (file_get_contents($path) ?: '') : '';
        $existing = $this->existingLines($contents);

        $missing = array_values(array_filter(
            self::MANAGED_PATHS,
            fn (string $line): bool => ! in_array($line, $existing, true),
        ));

        if ($missing === []) {
            return [];
        }

        $append = in_array(self::MARKER, $existing, true) ? [] : [self::MARKER];
        $append = [...$append, ...$missing];

        $prefix = ($contents === '' || str_ends_with($contents, "\n")) ? $contents : $contents."\n";

        $this->write($path, $prefix.implode("\n", $append)."\n");

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function existingLines(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== '',
        ));
    }

    private function write(string $path, string $contents): void
    {
        $tmp = tempnam(dirname($path), 'gitignore-');

        if ($tmp === false) {
            throw new DeskhandException("Unable to create a temporary file for {$path}.");
        }

        if (file_put_contents($tmp, $contents) === false || ! rename($tmp, $path)) {
            @unlink($tmp);
            throw new DeskhandException("Unable to write {$path}.");
        }
    }
}
