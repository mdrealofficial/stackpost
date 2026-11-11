<?php

namespace Modules\AppPublishing\Support;

class PublishingNetworkConfigRegistry
{
    protected array $configs = [];

    public function register(string $providerKey, array $config): self
    {
        $this->configs[strtolower(trim($providerKey))] = $config;

        return $this;
    }

    public function get(string $providerKey, array $default = []): array
    {
        $key = strtolower(trim($providerKey));

        return $this->configs[$key] ?? $default;
    }
}
