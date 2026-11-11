<?php

namespace Modules\AppIntegrations\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppIntegrations\Support\IntegrationCatalog;

#[Title('API Integration')]
class IntegrationHub extends Component
{
    #[Url(as: 'provider')]
    public string $activeProvider = '';

    public string $loadedProvider = '';

    /**
     * @var array<string, string>
     */
    public array $providerState = [];

    protected OptionStore $options;

    protected IntegrationCatalog $catalog;

    public function boot(OptionStore $options, IntegrationCatalog $catalog): void
    {
        $this->options = $options;
        $this->catalog = $catalog;
    }

    public function mount(): void
    {
        $this->syncActiveProvider();
    }

    public function updatedActiveProvider(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->syncActiveProvider();
    }

    public function switchProvider(string $provider): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->providerState = [];
        $this->activeProvider = $provider;
        $this->syncActiveProvider();
    }

    public function saveProvider(): void
    {
        if ($this->currentProvider() === []) {
            return;
        }

        $validated = $this->validate($this->providerValidationRules(), [], $this->providerAttributeNames());

        foreach (($this->currentProvider()['fields'] ?? []) as $field) {
            if (! $this->shouldPersistField($field)) {
                continue;
            }

            $key = (string) $field['key'];
            $this->options->set(
                $this->catalog->optionKey($this->activeProvider, $key),
                data_get($validated, "providerState.{$key}", '')
            );
        }

        $this->dispatch('integration-config-saved');
    }

    public function render(): View
    {
        return view('appintegrations::livewire.integration-hub', [
            'providers' => $this->catalog->items(),
            'currentProvider' => $this->currentProvider(),
            'providerCapabilities' => function_exists('channel_capabilities_for_provider')
                ? channel_capabilities_for_provider($this->activeProvider)
                : [],
            'statusOptions' => $this->toggleOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('API Integration'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentProvider(): array
    {
        return $this->catalog->get($this->activeProvider);
    }

    protected function loadProviderState(): void
    {
        $state = [];

        foreach (($this->currentProvider()['fields'] ?? []) as $field) {
            $key = (string) $field['key'];
            $default = (string) ($field['default'] ?? '');
            if (! $this->shouldPersistField($field)) {
                $state[$key] = $default;
                continue;
            }

            $value = $this->options->get($this->catalog->optionKey($this->activeProvider, $key), $default);
            $state[$key] = filled($value) ? (string) $value : $default;
        }

        $this->providerState = $state;
        $this->loadedProvider = $this->activeProvider;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function providerValidationRules(): array
    {
        $rules = [];

        foreach (($this->currentProvider()['fields'] ?? []) as $field) {
            $path = "providerState.{$field['key']}";

            $rules[$path] = match ($field['type']) {
                'toggle' => ['required', 'in:0,1'],
                'select' => ['required', Rule::in(array_keys($field['options'] ?? []))],
                'textarea' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function providerAttributeNames(): array
    {
        $names = [];

        foreach (($this->currentProvider()['fields'] ?? []) as $field) {
            $names["providerState.{$field['key']}"] = (string) $field['label'];
        }

        return $names;
    }

    protected function syncActiveProvider(): void
    {
        if (! $this->catalog->has($this->activeProvider)) {
            $this->activeProvider = (string) (array_key_first($this->catalog->items()) ?? '');
        }

        $this->loadProviderState();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function shouldPersistField(array $field): bool
    {
        $key = (string) ($field['key'] ?? '');
        $isReadonly = (bool) ($field['readonly'] ?? false);

        return ! $isReadonly && $key !== 'callback_url';
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }
}
