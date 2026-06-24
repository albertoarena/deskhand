<?php

declare(strict_types=1);

namespace Deskhand\Exception;

final class DatabaseProvisionException extends DeskhandException
{
    protected const int EXIT_CODE = 5;
}
