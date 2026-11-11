<?php

namespace Modules\AdminNotifications\DataObjects;

use Carbon\CarbonInterface;

class NotificationFeedItem
{
    public function __construct(
        public string $actionKey,
        public string $title,
        public string $message,
        public ?string $url,
        public ?CarbonInterface $read_at,
        public ?CarbonInterface $created_at,
        public string $source = 'manual',
        public bool $isGlobal = false,
    ) {
    }

    public function resolvedTitle(): string
    {
        return $this->title !== '' ? $this->title : __('Notification');
    }

    public function resolvedMessage(): string
    {
        return $this->message;
    }
}
