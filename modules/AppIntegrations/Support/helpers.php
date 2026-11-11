<?php

use Modules\AppIntegrations\Support\IntegrationCatalog;

if (! function_exists('integration_item_registry')) {
    function integration_item_registry(): IntegrationCatalog
    {
        return app(IntegrationCatalog::class);
    }
}

if (! function_exists('register_integration_item')) {
    function register_integration_item(string $key, array $definition): IntegrationCatalog
    {
        return integration_item_registry()->register($key, $definition);
    }
}

if (! function_exists('integration_items')) {
    function integration_items(): array
    {
        return integration_item_registry()->items();
    }
}

if (! function_exists('integration_item')) {
    function integration_item(string $key): array
    {
        return integration_item_registry()->get($key);
    }
}

if (! function_exists('integration_item_state')) {
    function integration_item_state(string $key): array
    {
        $item = integration_item($key);

        if ($item === []) {
            return [
                'enabled' => false,
                'configured' => false,
                'ready' => false,
                'missing_fields' => [],
                'values' => [],
                'item' => [],
            ];
        }

        $options = app(\Modules\AdminSettings\Support\OptionStore::class);
        $values = [];

        foreach (($item['fields'] ?? []) as $field) {
            $fieldKey = (string) $field['key'];
            $defaultValue = (string) ($field['default'] ?? '');
            $isReadonly = (bool) ($field['readonly'] ?? false);

            if ($isReadonly || $fieldKey === 'callback_url') {
                $values[$fieldKey] = $defaultValue;
                continue;
            }

            $values[$fieldKey] = (string) $options->get(
                integration_item_registry()->optionKey($key, $fieldKey),
                $defaultValue
            );
        }

        $requiredFields = $item['required_fields'] ?? [];
        $missingFields = collect($requiredFields)
            ->filter(fn (string $field): bool => blank($values[$field] ?? null))
            ->values()
            ->all();

        $enabled = ($values['status'] ?? '0') === '1';
        $configured = $missingFields === [];

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'ready' => $enabled && $configured,
            'missing_fields' => $missingFields,
            'values' => $values,
            'item' => $item,
        ];
    }
}

if (! function_exists('integration_sidebar_items')) {
    function integration_sidebar_items(): array
    {
        $items = integration_items();
        $defaultKey = array_key_first($items);
        $request = request();
        $baseUrl = $request ? rtrim($request->getBaseUrl(), '/') : '';
        $current = $request?->query('provider', $defaultKey);

        return collect($items)
            ->map(function (array $item, string $key) use ($baseUrl, $current): array {
                return [
                    'label' => $item['label'],
                    'route' => $baseUrl.route('admin.integrations', ['provider' => $key], absolute: false),
                    'active' => request()->routeIs('admin.integrations') && $current === $key,
                    'order' => (int) ($item['order'] ?? 100),
                ];
            })
            ->values()
            ->all();
    }
}
