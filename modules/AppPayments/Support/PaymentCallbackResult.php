<?php

namespace Modules\AppPayments\Support;

class PaymentCallbackResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $message = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $customerId = null,
        public readonly array $meta = [],
    ) {}

    public static function success(
        string $transactionId,
        ?float $amount = null,
        ?string $currency = null,
        array $meta = [],
        ?string $subscriptionId = null,
        ?string $customerId = null,
        ?string $message = null,
    ): self {
        return new self('success', $transactionId, $amount, $currency, $message, $subscriptionId, $customerId, $meta);
    }

    public static function pending(?string $message = null, array $meta = []): self
    {
        return new self('pending', null, null, null, $message, null, null, $meta);
    }

    public static function failed(?string $message = null, array $meta = []): self
    {
        return new self('failed', null, null, null, $message, null, null, $meta);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
