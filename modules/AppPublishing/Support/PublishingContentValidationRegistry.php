<?php

namespace Modules\AppPublishing\Support;

class PublishingContentValidationRegistry
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

    public function validate(string $providerKey, array $context = []): ?array
    {
        $validator = $this->get($providerKey);

        if (! $validator) {
            return null;
        }

        $result = $validator($context);

        if (! is_array($result)) {
            return null;
        }

        $field = trim((string) ($result['field'] ?? ''));
        $target = trim((string) ($result['target'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));

        if (($field === '' && $target === '') || $message === '') {
            return null;
        }

        return [
            'field' => $field,
            'target' => $target,
            'message' => $message,
        ];
    }
}
