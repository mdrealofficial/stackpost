<?php

namespace Modules\AppPayments\Support;

use Closure;

class PaymentGatewayDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $type = 'one_time',
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        public readonly array $display = [],
        public readonly ?string $reportLabel = null,
        public readonly array $capabilities = [],
        public readonly array $callbacks = [],
        public readonly int $sort = 100,
        public readonly bool|Closure $enabled = true,
    ) {}

    public function isEnabled(): bool
    {
        return is_bool($this->enabled)
            ? $this->enabled
            : (bool) app()->call($this->enabled);
    }

    public function display(string $key, mixed $default = null): mixed
    {
        return data_get($this->display, $key, $default);
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function callback(string $name): callable|string|null
    {
        return $this->callbacks[$name] ?? null;
    }
}
