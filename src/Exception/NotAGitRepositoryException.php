<?php

declare(strict_types=1);

namespace Deskhand\Exception;

final class NotAGitRepositoryException extends DeskhandException
{
    protected const int EXIT_CODE = 2;
}
