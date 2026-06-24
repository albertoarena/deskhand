<?php

declare(strict_types=1);

namespace Deskhand\Tests\Fakes;

use Deskhand\Core\Env\EnvMaterializer;

final class FakeEnvMaterializer implements EnvMaterializer
{
    /** @var array<string, array<string, string>> in-memory env files */
    private array $files = [];

    /** @var list<string> paths written via writeEnv */
    public array $written = [];

    /**
     * @param  array<string, string>  $values
     */
    public function seed(string $path, array $values): void
    {
        $this->files[$path] = $values;
    }

    public function read(string $envPath): array
    {
        return $this->files[$envPath] ?? [];
    }

    public function writeEnv(string $baseEnvPath, string $targetPath, array $overrides): void
    {
        $this->files[$targetPath] = array_merge($this->read($baseEnvPath), $overrides);
        $this->written[] = $targetPath;
    }
}
