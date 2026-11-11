<?php

namespace Modules\AppPayments\Support;

use Illuminate\Support\Arr;
use Modules\AppPayments\Contracts\PaymentGateway;

class PaymentManager
{
    protected array $definitions = [];

    protected array $resolvers = [];

    public function register(PaymentGatewayDefinition $definition, callable|string $resolver): void
    {
        $key = $this->normalizeKey($definition->key);

        $this->definitions[$key] = $definition;
        $this->resolvers[$key] = $resolver;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->definitions, $this->normalizeKey($key));
    }

    public function definition(string $key): PaymentGatewayDefinition
    {
        $key = $this->normalizeKey($key);

        abort_unless(isset($this->definitions[$key]), 404);

        return $this->definitions[$key];
    }

    public function supports(string $key, string $capability): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        return $this->definition($key)->supports($capability);
    }

    public function call(string $key, string $callback, array $arguments = []): mixed
    {
        $definition = $this->definition($key);
        $resolver = $definition->callback($callback);

        if ($resolver === null) {
            throw new \RuntimeException(sprintf('Payment gateway [%s] does not define callback [%s].', $key, $callback));
        }

        return is_string($resolver)
            ? app()->call([app($resolver), '__invoke'], $arguments)
            : app()->call($resolver, $arguments);
    }

    public function gateway(string $key): PaymentGateway
    {
        $key = $this->normalizeKey($key);
        $resolver = $this->resolvers[$key] ?? null;

        abort_unless($resolver !== null, 404);

        $gateway = is_string($resolver) ? app($resolver) : app()->call($resolver);

        if (! $gateway instanceof PaymentGateway) {
            throw new \RuntimeException(sprintf('Payment gateway [%s] must implement %s.', $key, PaymentGateway::class));
        }

        return $gateway;
    }

    public function available(): array
    {
        return collect($this->definitions)
            ->filter(fn (PaymentGatewayDefinition $definition) => $definition->isEnabled())
            ->sortBy(fn (PaymentGatewayDefinition $definition) => [$definition->sort, $definition->title])
            ->values()
            ->all();
    }

    protected function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
