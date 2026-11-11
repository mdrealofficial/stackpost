<?php

namespace Modules\AppPayments\Support;

class PaymentGatewaySettingsRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $sections = [];

    public function register(string $key, array $definition): static
    {
        $definition['key'] = $key;
        $definition['order'] = (int) ($definition['order'] ?? 100);
        $definition['state'] = (array) ($definition['state'] ?? []);
        $definition['rules'] = (array) ($definition['rules'] ?? []);
        $definition['aliases'] = array_values(array_filter(array_map('strval', (array) ($definition['aliases'] ?? []))));

        $this->sections[$key] = $definition;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return collect($this->sections)
            ->sortBy(fn (array $section) => [$section['order'] ?? 100, $section['title'] ?? $section['key']])
            ->values()
            ->all();
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->sections)) {
            return true;
        }

        foreach ($this->sections as $section) {
            if (in_array($key, (array) ($section['aliases'] ?? []), true)) {
                return true;
            }
        }

        return false;
    }
}
