<?php

declare(strict_types=1);

namespace Deskhand\Exception;

final class VerificationFailedException extends DeskhandException
{
    protected const int EXIT_CODE = 6;
}
