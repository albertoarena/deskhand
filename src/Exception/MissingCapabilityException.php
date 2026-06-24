<?php

declare(strict_types=1);

namespace Deskhand\Exception;

final class MissingCapabilityException extends DeskhandException
{
    protected const int EXIT_CODE = 3;
}
