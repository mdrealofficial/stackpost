<?php

namespace Modules\AppPayments\Support;

use Modules\AppPayments\Contracts\PaymentCheckoutTypeHandler;

class PaymentCheckoutTypeRegistry
{
    protected array $handlers = [];

    public function register(string $kind, string $handlerClass): void
    {
        $this->handlers[strtolower(trim($kind))] = $handlerClass;
    }

    public function resolve(PaymentCheckout $checkout): PaymentCheckoutTypeHandler
    {
        $kind = strtolower((string) ($checkout->meta['checkout_kind'] ?? 'plan'));
        $handler = $this->handlers[$kind] ?? null;

        if (! $handler) {
            throw new \RuntimeException(sprintf('Payment checkout kind [%s] is not registered.', $kind));
        }

        $instance = app($handler);

        if (! $instance instanceof PaymentCheckoutTypeHandler) {
            throw new \RuntimeException(sprintf('Handler [%s] must implement %s.', $handler, PaymentCheckoutTypeHandler::class));
        }

        return $instance;
    }
}
