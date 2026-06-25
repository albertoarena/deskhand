<?php

declare(strict_types=1);

namespace Deskhand\Core\Capability;

use Deskhand\Core\Process\ProcessRunner;
use JsonException;

/**
 * Probes the host and project for the capabilities `up` needs (§8).
 *
 * Host binaries are detected via `which` through the {@see ProcessRunner} seam
 * (so this stays unit-testable and is the only place capability probing touches
 * the process layer). Project facts are read directly from the filesystem —
 * this detector is itself the seam for those reads.
 */
final class SystemCapabilityDetector implements CapabilityDetector
{
    public function __construct(
        private readonly ProcessRunner $process,
        private readonly string $workingDirectory,
    ) {}

    public function hasComposer(): bool
    {
        return $this->binaryExists('composer');
    }

    public function hasNpm(): bool
    {
        return $this->binaryExists('npm');
    }

    public function hasYarn(): bool
    {
        return $this->binaryExists('yarn');
    }

    public function hasMysqlClient(): bool
    {
        return $this->binaryExists('mysql');
    }

    public function hasFrontend(string $projectPath): bool
    {
        return is_file($projectPath.'/package.json');
    }

    public function detectPackageManager(string $projectPath): ?string
    {
        $yarn = is_file($projectPath.'/yarn.lock');
        $npm = is_file($projectPath.'/package-lock.json');

        if ($yarn && ! $npm) {
            return 'yarn';
        }

        if ($npm && ! $yarn) {
            return 'npm';
        }

        // Both or neither: ambiguous — the caller applies the config fallback.
        return null;
    }

    public function hasParallelTesting(string $projectPath): bool
    {
        return $this->packageAvailable($projectPath, 'brianium/paratest');
    }

    public function needsStorageLink(string $projectPath): bool
    {
        $source = $projectPath.'/storage/app/public';
        $link = $projectPath.'/public/storage';

        return is_dir($source) && ! is_link($link) && ! is_dir($link);
    }

    private function binaryExists(string $binary): bool
    {
        return $this->process->run(['which', $binary], $this->workingDirectory)->successful();
    }

    private function packageAvailable(string $projectPath, string $package): bool
    {
        if (is_dir($projectPath.'/vendor/'.$package)) {
            return true;
        }

        $lock = $this->readJson($projectPath.'/composer.lock');

        if ($lock !== null) {
            foreach (['packages', 'packages-dev'] as $section) {
                $packages = $lock[$section] ?? null;

                if (! is_array($packages)) {
                    continue;
                }

                foreach ($packages as $pkg) {
                    if (is_array($pkg) && ($pkg['name'] ?? null) === $package) {
                        return true;
                    }
                }
            }
        }

        $json = $this->readJson($projectPath.'/composer.json');

        if ($json !== null) {
            foreach (['require', 'require-dev'] as $section) {
                $deps = $json[$section] ?? null;

                if (is_array($deps) && array_key_exists($package, $deps)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
