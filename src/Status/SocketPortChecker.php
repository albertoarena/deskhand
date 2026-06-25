<?php

declare(strict_types=1);

namespace Deskhand\Status;

/**
 * Detects a port in use by attempting a short-timeout connection to it on
 * 127.0.0.1 — if something accepts, the port is occupied.
 */
final class SocketPortChecker implements PortChecker
{
    public function isInUse(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
