<?php

namespace App\Exceptions;

use RuntimeException;

class DemoModeRestrictedException extends RuntimeException
{
    public static function withDefaultMessage(): self
    {
        return new self(__('Demo mode is enabled. Add, edit, and delete actions are disabled.'));
    }
}
