<?php

declare(strict_types=1);

namespace Deskhand\Core\Planning;

use Deskhand\Core\Config\Config;
use Deskhand\Core\Naming\DatabaseNamer;
use Deskhand\Core\Naming\PortAllocator;
use Deskhand\Core\Naming\RedisNamer;
use Deskhand\Core\Naming\Slug;
use Deskhand\Core\Registry\DatabaseRecord;
use Deskhand\Core\Registry\Registry;
use Deskhand\Core\Registry\WorktreeRecord;
use Deskhand\Core\Url\UrlResolver;
use Deskhand\Exception\WorktreeExistsException;

/**
 * Computes the full {@see WorktreeRecord} for an `up` run from the §7 naming
 * rules, the configured port ranges, the Redis policy and the URL strategy —
 * pure derivation, no side effects. It also enforces the §7 slug-collision
 * rule: a slug already claimed by a *different* branch fails; the same branch
 * is the idempotent re-run path.
 */
final class WorktreePlanner
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlResolver $urlResolver,
        private readonly Registry $registry,
    ) {}

    public function plan(PlanRequest $request): WorktreeRecord
    {
        $slug = Slug::fromBranch($request->branch);

        $existing = $this->registry->find($slug);

        if ($existing !== null && $existing->branch !== $request->branch) {
            throw new WorktreeExistsException(
                "slug '{$slug}' is already used by branch '{$existing->branch}'. Choose a different branch name or pass --path."
            );
        }

        $ports = (new PortAllocator($this->config->servePortRange, $this->config->vitePortRange))->forSlug($slug);

        return new WorktreeRecord(
            slug: $slug,
            branch: $request->branch,
            path: $request->pathFlag ?? '.claude/worktrees/'.$slug,
            createdAt: $request->createdAt,
            db: $this->planDatabase($request, $slug),
            ports: $ports,
            redis: (new RedisNamer)->forSlug($slug, $request->redisIsolated),
            url: $this->urlResolver->resolve($request->urlFlag, $slug, $ports->serve, $request->baseEnv),
        );
    }

    private function planDatabase(PlanRequest $request, string $slug): DatabaseRecord
    {
        if ($request->shared) {
            return new DatabaseRecord(
                engine: $request->engine,
                shared: true,
                main: $request->baseEnv['DB_DATABASE'] ?? '',
                testDbs: [],
            );
        }

        $base = $request->baseEnv['DB_DATABASE'] ?? basename($request->repoRoot);
        $namer = new DatabaseNamer;

        $testDbs = [];

        if ($request->engine === DatabaseNamer::ENGINE_MYSQL) {
            for ($n = 1; $n <= $request->testDbCount; $n++) {
                $testDbs[] = $namer->test($request->engine, $slug, $base, $n);
            }
        }

        return new DatabaseRecord(
            engine: $request->engine,
            shared: false,
            main: $namer->main($request->engine, $slug, $base),
            testDbs: $testDbs,
        );
    }
}
