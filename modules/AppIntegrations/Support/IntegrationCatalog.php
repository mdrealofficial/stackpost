<?php

namespace Modules\AppIntegrations\Support;

class IntegrationCatalog
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];

    public function register(string $key, array $definition): static
    {
        $existing = $this->items[$key] ?? [];
        $this->items[$key] = array_replace_recursive($existing, $definition + ['key' => $key]);

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function items(): array
    {
        $items = $this->items;

        uasort($items, fn (array $a, array $b) => (($a['order'] ?? 100) <=> ($b['order'] ?? 100)));

        return $items;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return $this->items()[$key] ?? [];
    }

    public function optionKey(string $key, string $field): string
    {
        return "integration_{$key}_{$field}";
    }
}
