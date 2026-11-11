<?php

namespace Modules\AppAutomation\Exceptions;

use RuntimeException;
use Throwable;

class AutomationNodeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }
}
