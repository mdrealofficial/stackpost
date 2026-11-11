<?php

namespace Modules\AppPublishing\Support;

class PublishingValidationFieldTargetRegistry
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $targets = [];

    public function register(string $providerKey, array $targets): self
    {
        $key = strtolower(trim($providerKey));

        if ($key !== '') {
            $this->targets[$key] = [
                ...($this->targets[$key] ?? []),
                ...$targets,
            ];
        }

        return $this;
    }

    public function resolve(string $providerKey, string $target, string $default = 'composer.caption'): string
    {
        $key = strtolower(trim($providerKey));
        $targetKey = trim($target);

        if ($key === '' || $targetKey === '') {
            return $default;
        }

        if (isset($this->targets[$key][$targetKey])) {
            return (string) $this->targets[$key][$targetKey];
        }

        if ($targetKey === 'caption') {
            return 'composer.caption';
        }

        if (str_contains($targetKey, '.')) {
            return $targetKey;
        }

        return 'composer.network_options.'.$key.'.'.$targetKey;
    }
}
