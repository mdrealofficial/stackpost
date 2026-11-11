<?php

namespace Modules\AppPayments\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppPayments\Support\PaymentGatewaySettingsRegistry;
use Modules\AppPayments\Support\PaymentManager;

#[Title('Payment Gateways')]
class PaymentGateways extends Component
{
    protected OptionStore $options;

    protected PaymentGatewaySettingsRegistry $registry;

    protected PaymentManager $payments;

    public array $state = [];

    public function boot(
        OptionStore $options,
        PaymentGatewaySettingsRegistry $registry,
        PaymentManager $payments
    ): void {
        $this->options = $options;
        $this->registry = $registry;
        $this->payments = $payments;
    }

    public function mount(): void
    {
        foreach ($this->registeredSections() as $section) {
            foreach ((array) ($section['state'] ?? []) as $key => $default) {
                $this->state[$key] = (string) $this->options->get($key, $default);
            }
        }
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $validatedState = (array) ($validated['state'] ?? []);

        foreach ($this->registeredSections() as $section) {
            $sectionState = [];

            foreach ((array) ($section['state'] ?? []) as $key => $default) {
                $sectionState[$key] = (string) ($validatedState[$key] ?? $default);
            }

            if (isset($section['save']) && is_callable($section['save'])) {
                app()->call($section['save'], [
                    'state' => $sectionState,
                    'options' => $this->options,
                    'component' => $this,
                    'section' => $section,
                ]);

                continue;
            }

            foreach ($sectionState as $key => $value) {
                $this->options->set($key, $value);
            }
        }

        $this->dispatch('settings-saved');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->registeredSections() as $section) {
            foreach ((array) ($section['rules'] ?? []) as $key => $value) {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function registeredSections(): array
    {
        return $this->registry->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fallbackSections(): array
    {
        return collect($this->payments->available())
            ->reject(fn ($definition) => $this->registry->has($definition->key))
            ->sortBy(fn ($definition) => [$definition->sort, $definition->title])
            ->map(function ($definition): array {
                return [
                    'key' => $definition->key,
                    'title' => $definition->title,
                    'description' => $definition->description ?: __('This gateway does not provide a dedicated admin configuration form yet.'),
                    'icon' => $definition->icon,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sectionsForView(): array
    {
        return collect($this->registeredSections())
            ->map(function (array $section): array {
                $data = [];

                if (isset($section['data']) && is_callable($section['data'])) {
                    $data = (array) app()->call($section['data'], [
                        'state' => $this->state,
                        'component' => $this,
                        'section' => $section,
                    ]);
                }

                unset($section['data']);

                return array_merge($section, ['data' => $data]);
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('apppayments::admin.payment-gateways', [
            'sections' => $this->sectionsForView(),
            'fallbackSections' => collect($this->fallbackSections()),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment Gateways'),
        ]);
    }
}
