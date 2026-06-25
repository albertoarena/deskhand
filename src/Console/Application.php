<?php

declare(strict_types=1);

namespace Deskhand\Console;

use Deskhand\Console\Command\DownCommand;
use Deskhand\Console\Command\UpCommand;
use Deskhand\Down\DefaultDownRunnerFactory;
use Deskhand\Up\DefaultUpRunnerFactory;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * The deskhand Symfony Console application.
 *
 * Commands (up/down/list/status) are registered here as they are implemented
 * in later build phases (see implementation.md §16.6).
 */
final class Application extends BaseApplication
{
    public const string NAME = 'deskhand';

    public const string VERSION = '0.1.0-dev';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->addCommand(new UpCommand(new DefaultUpRunnerFactory));
        $this->addCommand(new DownCommand(new DefaultDownRunnerFactory));
    }
}
