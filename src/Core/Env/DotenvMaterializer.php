<?php

declare(strict_types=1);

namespace Deskhand\Core\Env;

use Deskhand\Exception\DeskhandException;

/**
 * Generic dotenv reader/writer (§4.1 step 4). It only handles `KEY=value`
 * parsing and merging — Laravel-specific override values (DB name, APP_NAME tag,
 * ports, Redis, safe test drivers) are supplied by the caller.
 *
 * writeEnv copies the base file and patches it line-by-line: overridden keys are
 * replaced in place, new keys appended, and every other line (comments, blank
 * lines, untouched values) is preserved verbatim. The result is written
 * atomically via rename, which replaces the target directory entry — so it
 * never follows or writes through a pre-existing symlink and never creates one
 * (safety invariant #5: copy `.env`, never symlink it).
 */
final class DotenvMaterializer implements EnvMaterializer
{
    private const string ASSIGNMENT = '/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/';

    public function read(string $envPath): array
    {
        if (! is_file($envPath)) {
            return [];
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            return [];
        }

        $result = [];

        foreach ($this->splitLines($contents) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match(self::ASSIGNMENT, $line, $m) === 1) {
                $result[$m[1]] = $this->unquote(trim($m[2]));
            }
        }

        return $result;
    }

    public function writeEnv(string $baseEnvPath, string $targetPath, array $overrides): void
    {
        $base = is_file($baseEnvPath) ? (file_get_contents($baseEnvPath) ?: '') : '';
        $lines = $base === '' ? [] : $this->splitLines($base);

        // Drop the trailing empty element produced by a final newline, so
        // appended keys don't land after a spurious blank line.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $applied = [];
        $out = [];

        foreach ($lines as $line) {
            $key = preg_match(self::ASSIGNMENT, $line, $m) === 1 ? $m[1] : null;

            if ($key !== null && array_key_exists($key, $overrides)) {
                $out[] = $this->format($key, $overrides[$key]);
                $applied[$key] = true;
            } else {
                $out[] = $line;
            }
        }

        foreach ($overrides as $key => $value) {
            if (! isset($applied[$key])) {
                $out[] = $this->format($key, $value);
            }
        }

        $this->writeAtomically($targetPath, implode("\n", $out)."\n");
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $contents);

        return $lines === false ? [] : $lines;
    }

    private function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if ($first === '"' && $last === '"') {
                return str_replace(['\\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
            }

            if ($first === "'" && $last === "'") {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    private function format(string $key, string $value): string
    {
        return $key.'='.$this->encode($value);
    }

    private function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('#^[A-Za-z0-9_.:/@-]+$#', $value) === 1) {
            return $value;
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"'.$escaped.'"';
    }

    private function writeAtomically(string $targetPath, string $contents): void
    {
        $dir = dirname($targetPath);

        if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
            throw new DeskhandException("Unable to create the directory {$dir} for {$targetPath}.");
        }

        $tmp = tempnam($dir, 'env-');

        if ($tmp === false) {
            throw new DeskhandException("Unable to create a temporary file for {$targetPath}.");
        }

        if (file_put_contents($tmp, $contents) === false || ! rename($tmp, $targetPath)) {
            @unlink($tmp);
            throw new DeskhandException("Unable to write the env file at {$targetPath}.");
        }
    }
}
