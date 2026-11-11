<?php

namespace Modules\AppPublishing\Support;

use Modules\AppPublishing\Contracts\PostPublisher;

class PublishingChannelRegistry
{
    protected array $publishers = [];

    public function register(string $providerKey, PostPublisher $publisher): self
    {
        $this->publishers[strtolower(trim($providerKey))] = $publisher;

        return $this;
    }

    public function get(string $providerKey): ?PostPublisher
    {
        $key = strtolower(trim($providerKey));

        return $this->publishers[$key] ?? null;
    }

    public function has(string $providerKey): bool
    {
        return $this->get($providerKey) !== null;
    }
}
