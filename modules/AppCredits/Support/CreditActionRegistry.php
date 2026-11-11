<?php

namespace Modules\AppCredits\Support;

class CreditActionRegistry
{
    protected array $items = [];

    public function register(array $item): static
    {
        $key = trim((string) ($item['key'] ?? ''));

        if ($key === '') {
            throw new \InvalidArgumentException('Credit action key is required.');
        }

        $item['key'] = $key;
        $item['label'] = (string) ($item['label'] ?? str($key)->headline()->value());
        $item['description'] = isset($item['description']) ? (string) $item['description'] : null;
        $item['default_cost'] = max(0, (int) ($item['default_cost'] ?? 1));
        $item['order'] = (int) ($item['order'] ?? 100);
        $item['plan_key'] = (string) ($item['plan_key'] ?? $this->planKeyFor($key));

        $this->items[$key] = $item;

        return $this;
    }

    public function all(): array
    {
        $items = array_values($this->items);
        usort($items, fn (array $a, array $b): int => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

        return $items;
    }

    public function get(string $key): ?array
    {
        $key = trim($key);

        return $key !== '' ? ($this->items[$key] ?? null) : null;
    }

    public function planKeyFor(string $actionKey): string
    {
        return 'credit_cost_'.str($actionKey)->snake()->replace('.', '_')->value();
    }
}
