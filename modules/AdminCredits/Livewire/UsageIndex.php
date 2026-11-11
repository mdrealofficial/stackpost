<?php

namespace Modules\AdminCredits\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppCredits\Models\CreditUsageLog;

#[Title('Credit Usage')]
class UsageIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $action = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $baseQuery = CreditUsageLog::query()
            ->with(['user', 'plan'])
            ->when($this->action !== '', fn ($query) => $query->where('action_key', $this->action))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('action_key', 'like', $term)
                        ->orWhere('feature', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('username', 'like', $term));
                });
            });

        $logs = (clone $baseQuery)->latest('id')->paginate(20);
        $metrics = [
            'entries' => (clone $baseQuery)->count(),
            'credits' => (int) (clone $baseQuery)->sum('amount'),
            'users' => (clone $baseQuery)->distinct('user_id')->count('user_id'),
            'actions' => (clone $baseQuery)->distinct('action_key')->count('action_key'),
        ];

        $actionOptions = CreditUsageLog::query()
            ->select('action_key')
            ->distinct()
            ->orderBy('action_key')
            ->pluck('action_key')
            ->mapWithKeys(fn ($key) => [$key => $key])
            ->all();

        return view('admincredits::livewire.usage-index', [
            'logs' => $logs,
            'metrics' => $metrics,
            'actionOptions' => ['' => __('All actions')] + $actionOptions,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Credit Usage'),
        ]);
    }
}
