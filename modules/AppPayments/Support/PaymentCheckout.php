<?php

namespace Modules\AppPayments\Support;

class PaymentCheckout
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $gatewayType,
        public readonly int $userId,
        public readonly int $planId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $returnUrl,
        public readonly string $cancelUrl,
        public readonly array $meta = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gateway: (string) ($data['gateway'] ?? ''),
            gatewayType: (string) ($data['gateway_type'] ?? 'one_time'),
            userId: (int) ($data['user_id'] ?? 0),
            planId: (int) ($data['plan_id'] ?? 0),
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            returnUrl: (string) ($data['return_url'] ?? ''),
            cancelUrl: (string) ($data['cancel_url'] ?? ''),
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'gateway_type' => $this->gatewayType,
            'user_id' => $this->userId,
            'plan_id' => $this->planId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'meta' => $this->meta,
        ];
    }
}
