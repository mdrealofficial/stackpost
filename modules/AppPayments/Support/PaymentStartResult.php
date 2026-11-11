<?php

namespace Modules\AppPayments\Support;

class PaymentStartResult
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly array $meta = [],
    ) {}

    public static function redirect(string $redirectUrl, array $meta = []): self
    {
        return new self($redirectUrl, $meta);
    }
}
