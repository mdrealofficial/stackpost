<?php

namespace Modules\AdminCredits\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppCredits\Models\CreditTopupLedger;

#[Title('Credit Ledger')]
class LedgerIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $type = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $baseQuery = CreditTopupLedger::query()
            ->with(['user', 'creditPack', 'paymentHistory'])
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->whereHas('user', fn ($user) => $user
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term))
                        ->orWhereHas('creditPack', fn ($pack) => $pack->where('name', 'like', $term))
                        ->orWhere('type', 'like', $term);
                });
            });

        $entries = (clone $baseQuery)->latest('id')->paginate(20);
        $metrics = [
            'entries' => (clone $baseQuery)->count(),
            'purchases' => (clone $baseQuery)->where('type', 'purchase')->count(),
            'granted' => (int) (clone $baseQuery)->whereIn('type', ['purchase', 'adjustment'])->sum('amount'),
            'remaining' => (int) CreditTopupLedger::query()->sum('remaining'),
        ];

        return view('admincredits::livewire.ledger-index', [
            'entries' => $entries,
            'metrics' => $metrics,
            'typeOptions' => [
                '' => __('All types'),
                'purchase' => __('Purchase'),
                'consume' => __('Consume'),
                'adjustment' => __('Adjustment'),
                'expire' => __('Expire'),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Credit Ledger'),
        ]);
    }
}
