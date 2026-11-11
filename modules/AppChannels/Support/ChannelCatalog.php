<?php

namespace Modules\AppChannels\Support;

class ChannelCatalog
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $capabilities = [];

    public function register(string $key, array $definition): static
    {
        $existing = $this->capabilities[$key] ?? [];
        $this->capabilities[$key] = array_replace_recursive($existing, $definition + ['key' => $key]);

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function capabilities(): array
    {
        $capabilities = $this->capabilities;

        uasort($capabilities, fn (array $a, array $b) => (($a['order'] ?? 100) <=> ($b['order'] ?? 100)));

        return $capabilities;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->capabilities);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return $this->capabilities()[$key] ?? [];
    }
}
