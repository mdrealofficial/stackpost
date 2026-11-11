<?php

namespace Modules\AppAutomation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutomationDriveFileChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public array $payload) {}

    public function toArray(): array
    {
        return $this->payload;
    }
}
