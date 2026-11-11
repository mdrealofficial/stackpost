<?php

namespace Modules\AdminAffiliate\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAffiliate\Models\AffiliateProfile;

#[Title('Affiliate')]
class AdminAffiliateIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = AffiliateProfile::query()
            ->with('user:id,name,username,email,referral_code')
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('referral_code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('total_balance')
            ->orderByDesc('id');

        $profiles = $query->paginate(15);
        $allProfiles = AffiliateProfile::query()->get(['clicks', 'conversions', 'total_approved', 'total_balance']);

        return view('adminaffiliate::livewire.index', [
            'profiles' => $profiles,
            'filters' => [
                'q' => $this->search,
            ],
            'summary' => [
                'affiliates' => $allProfiles->count(),
                'clicks' => (int) $allProfiles->sum('clicks'),
                'conversions' => (int) $allProfiles->sum('conversions'),
                'approved' => (float) $allProfiles->sum('total_approved'),
                'balance' => (float) $allProfiles->sum('total_balance'),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Affiliate'),
        ]);
    }
}

