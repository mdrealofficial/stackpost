<?php

namespace Modules\AppPublishing\Support;

class PublishingOptionsRegistry
{
    protected array $views = [];

    public function register(string $providerKey, string $view): self
    {
        $this->views[strtolower(trim($providerKey))] = trim($view);

        return $this;
    }

    public function get(string $providerKey, ?string $default = null): ?string
    {
        $key = strtolower(trim($providerKey));

        return $this->views[$key] ?? $default;
    }
}
