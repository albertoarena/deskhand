<?php

declare(strict_types=1);

namespace Deskhand\Exception;

use RuntimeException;

/**
 * Base for all deskhand failures.
 *
 * Each subclass declares the process exit code for its failure class
 * (implementation.md §10); the command layer reads exitCode() to terminate
 * with a code scripts/CI/agents can branch on.
 */
class DeskhandException extends RuntimeException
{
    protected const int EXIT_CODE = 1;

    public function exitCode(): int
    {
        return static::EXIT_CODE;
    }
}
