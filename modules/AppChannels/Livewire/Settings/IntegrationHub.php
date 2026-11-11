<?php

namespace Modules\AppChannels\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppChannels\Support\IntegrationCatalog;

#[Title('App Channels')]
class IntegrationHub extends Component
{
    #[Url(as: 'provider')]
    public string $activeProvider = '';

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
        $this->syncActiveProvider();
    }

    public function switchProvider(string $provider): void
    {
        $this->activeProvider = $provider;
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
            $this->options->set($this->catalog->optionKey($this->activeProvider, $key), $validated["providerState.{$key}"] ?? '');
        }

        $this->dispatch('integration-config-saved');
    }

    public function render(): View
    {
        $providers = $this->catalog->providers();
        $providerStates = collect($providers)
            ->mapWithKeys(fn (array $provider, string $key): array => [$key => integration_item_state($key)])
            ->all();
        $currentProvider = $this->currentProvider();
        $currentProviderState = $currentProvider !== []
            ? ($providerStates[$this->activeProvider] ?? integration_item_state($this->activeProvider))
            : null;

        return view('appchannels::livewire.settings.integration-hub', [
            'providers' => $providers,
            'providerStates' => $providerStates,
            'currentProvider' => $currentProvider,
            'currentProviderState' => $currentProviderState,
            'statusOptions' => $this->toggleOptions(),
            'liveProviders' => collect($providerStates)->where('ready', true)->count(),
            'configuredProviders' => collect($providerStates)->where('configured', true)->count(),
            'disabledProviders' => collect($providerStates)->where('enabled', false)->count(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('App Channels'),
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

            $state[$key] = (string) $this->options->get($this->catalog->optionKey($this->activeProvider, $key), $default);
        }

        $this->providerState = $state;
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
            $this->activeProvider = (string) (array_key_first($this->catalog->providers()) ?? '');
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
