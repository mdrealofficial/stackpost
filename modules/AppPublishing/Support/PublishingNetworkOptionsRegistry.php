<?php

namespace Modules\AppPublishing\Support;

class PublishingNetworkOptionsRegistry
{
    /**
     * @var array<string, callable>
     */
    protected array $resolvers = [];

    public function register(string $providerKey, callable $resolver): self
    {
        $key = strtolower(trim($providerKey));

        if ($key !== '') {
            $this->resolvers[$key] = $resolver;
        }

        return $this;
    }

    public function resolve(string $providerKey, array $options = [], array $config = []): array
    {
        $normalizedOptions = $this->normalizeGenericOptions($options, $config);
        $resolver = $this->resolvers[strtolower(trim($providerKey))] ?? null;

        if (! $resolver) {
            return [
                'options' => $normalizedOptions,
                'media_type' => $this->defaultMediaType($normalizedOptions),
            ];
        }

        $resolved = $resolver($normalizedOptions, $config);

        if (! is_array($resolved)) {
            return [
                'options' => $normalizedOptions,
                'media_type' => $this->defaultMediaType($normalizedOptions),
            ];
        }

        $nextOptions = $normalizedOptions;

        if (array_key_exists('options', $resolved) && is_array($resolved['options'])) {
            $nextOptions = $resolved['options'];
        } else {
            $nextOptions = [
                ...$normalizedOptions,
                ...$resolved,
            ];
            unset($nextOptions['media_type']);
        }

        $nextOptions = $this->normalizeGenericOptions($nextOptions, $config);

        return [
            'options' => $nextOptions,
            'media_type' => isset($resolved['media_type']) && is_string($resolved['media_type']) && trim($resolved['media_type']) !== ''
                ? trim($resolved['media_type'])
                : $this->defaultMediaType($nextOptions),
        ];
    }

    protected function normalizeGenericOptions(array $options, array $config): array
    {
        $allowedPostTargets = collect((array) ($config['post_to_options'] ?? []))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $defaultPostTo = (string) ($config['default_post_to'] ?? ($allowedPostTargets[0] ?? 'feed'));
        $postTo = (string) ($options['post_to'] ?? $defaultPostTo);

        if (! in_array($postTo, $allowedPostTargets, true)) {
            $postTo = $defaultPostTo;
        }

        return [
            ...$options,
            'post_to' => $postTo,
        ];
    }

    protected function defaultMediaType(array $options): string
    {
        return match ((string) ($options['post_to'] ?? 'feed')) {
            'reels' => 'reel',
            'stories' => 'story',
            default => 'image',
        };
    }
}
