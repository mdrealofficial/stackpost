<?php

namespace Modules\AppAutomation\Support;

use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

final readonly class AutomationWorkspaceContext
{
    public function __construct(
        public User $owner,
        public ?Team $team,
    ) {}

    public function ownerUserId(): int
    {
        return (int) $this->owner->id;
    }

    public function teamId(): ?int
    {
        return $this->team ? (int) $this->team->id : null;
    }
}
