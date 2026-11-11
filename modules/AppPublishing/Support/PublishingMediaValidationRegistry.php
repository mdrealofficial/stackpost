<?php

namespace Modules\AppPublishing\Support;

class PublishingMediaValidationRegistry
{
    /**
     * @var array<string, callable>
     */
    protected array $validators = [];

    public function register(string $providerKey, callable $validator): self
    {
        $key = strtolower(trim($providerKey));

        if ($key !== '') {
            $this->validators[$key] = $validator;
        }

        return $this;
    }

    public function get(string $providerKey, ?callable $default = null): ?callable
    {
        $key = strtolower(trim($providerKey));

        if ($key === '') {
            return $default;
        }

        return $this->validators[$key] ?? $default;
    }

    public function validate(string $providerKey, array $context = []): ?string
    {
        $validator = $this->get($providerKey);

        if (! $validator) {
            return null;
        }

        $message = $validator($context);

        if (! is_string($message)) {
            return null;
        }

        $message = trim($message);

        return $message !== '' ? $message : null;
    }
}
