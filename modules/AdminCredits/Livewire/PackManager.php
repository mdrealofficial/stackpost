<?php

namespace Modules\AdminCredits\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminPlans\Support\CurrencyCatalog;
use Modules\AppCredits\Models\CreditPack;

#[Title('Credit Packs')]
class PackManager extends Component
{
    public ?string $statusMessage = null;

    public bool $isEditingPack = false;

    public ?int $editingPackId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $credits = '500';

    public string $price = '9.00';

    public string $currency = 'USD';

    public string $status = '1';

    public string $featured = '0';

    public string $sort = '0';

    public function mount(): void
    {
        $this->resetPackForm();
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->resetPackForm();
        $this->dispatch('credit-pack-modal-open');
    }

    public function openEditModal(int $packId): void
    {
        $pack = CreditPack::query()->findOrFail($packId);

        $this->resetValidation();
        $this->isEditingPack = true;
        $this->editingPackId = $pack->id;
        $this->name = (string) $pack->name;
        $this->slug = (string) $pack->slug;
        $this->description = (string) ($pack->description ?? '');
        $this->credits = (string) $pack->credits;
        $this->price = number_format((float) $pack->price, 2, '.', '');
        $this->currency = CurrencyCatalog::normalizeCode((string) ($pack->currency ?: 'USD'));
        $this->status = $pack->status ? '1' : '0';
        $this->featured = $pack->featured ? '1' : '0';
        $this->sort = (string) $pack->sort;

        $this->dispatch('credit-pack-modal-open');
    }

    public function savePack(): void
    {
        $pack = $this->editingPackId ? CreditPack::query()->findOrFail($this->editingPackId) : null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('credit_packs', 'slug')->ignore($pack?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'credits' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(CurrencyCatalog::codes())],
            'status' => ['required', 'in:0,1'],
            'featured' => ['required', 'in:0,1'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $selectedCurrency = CurrencyCatalog::find($validated['currency']) ?? CurrencyCatalog::find('USD');

        $payload = [
            'name' => trim($validated['name']),
            'slug' => trim((string) ($validated['slug'] ?? '')),
            'description' => trim((string) ($validated['description'] ?? '')),
            'credits' => (int) $validated['credits'],
            'price' => (float) $validated['price'],
            'currency' => CurrencyCatalog::normalizeCode($validated['currency']),
            'currency_symbol' => (string) ($selectedCurrency['symbol'] ?? '$'),
            'status' => (string) $validated['status'] === '1',
            'featured' => (string) $validated['featured'] === '1',
            'sort' => (int) ($validated['sort'] ?? 0),
        ];

        if ($pack) {
            $pack->update($payload);
            $this->statusMessage = __('Credit pack updated successfully.');
        } else {
            CreditPack::query()->create($payload);
            $this->statusMessage = __('Credit pack created successfully.');
        }

        $this->dispatch('credit-pack-modal-close');
        $this->resetPackForm();
    }

    public function deletePack(int $packId): void
    {
        CreditPack::query()->findOrFail($packId)->delete();

        $this->statusMessage = __('Credit pack removed successfully.');

        if ($this->editingPackId === $packId) {
            $this->dispatch('credit-pack-modal-close');
            $this->resetPackForm();
        }
    }

    protected function resetPackForm(): void
    {
        $nextSort = (int) (CreditPack::query()->max('sort') ?? -1) + 1;

        $this->isEditingPack = false;
        $this->editingPackId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->credits = '500';
        $this->price = '9.00';
        $this->currency = 'USD';
        $this->status = '1';
        $this->featured = '0';
        $this->sort = (string) max(0, $nextSort);
    }

    public function render(): View
    {
        $packs = CreditPack::query()
            ->orderByDesc('featured')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $packCount = $packs->count();
        $activeCount = $packs->where('status', true)->count();
        $featuredCount = $packs->where('featured', true)->count();

        return view('admincredits::livewire.pack-manager', [
            'packs' => $packs,
            'packCount' => $packCount,
            'activeCount' => $activeCount,
            'featuredCount' => $featuredCount,
            'currencyOptions' => CurrencyCatalog::options(),
            'selectedCurrency' => CurrencyCatalog::find($this->currency) ?? CurrencyCatalog::find('USD'),
            'toggleOptions' => [
                ['value' => '1', 'label' => 'Enable'],
                ['value' => '0', 'label' => 'Disable'],
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Credit Packs'),
        ]);
    }
}
