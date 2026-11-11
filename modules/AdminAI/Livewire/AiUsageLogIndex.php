<?php

namespace Modules\AdminAI\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminAI\Models\AiUsageLog;

class AiUsageLogIndex extends Component
{
    #[Url(as: 'q', except: '')]
    public string $userSearch = '';

    #[Url(as: 'provider', except: '')]
    public string $provider = '';

    #[Url(as: 'capability', except: '')]
    public string $capability = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    public function resetFilters(): void
    {
        $this->reset('userSearch', 'provider', 'capability', 'status', 'dateFrom', 'dateTo');
    }

    public function render(): View
    {
        $logs = AiUsageLog::query()
            ->with('user')
            ->when($this->userSearch !== '', function ($query): void {
                $search = trim($this->userSearch);

                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($this->provider !== '', fn ($query) => $query->where('provider', $this->provider))
            ->when($this->capability !== '', fn ($query) => $query->where('capability', $this->capability))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->limit(100)
            ->get();

        return view('adminai::livewire.logs-index', [
            'logs' => $logs,
            'providers' => AiUsageLog::query()->select('provider')->distinct()->orderBy('provider')->pluck('provider'),
            'capabilities' => AiUsageLog::query()->select('capability')->distinct()->orderBy('capability')->pluck('capability'),
            'statuses' => AiUsageLog::query()->select('status')->distinct()->orderBy('status')->pluck('status'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Usage Logs'),
        ]);
    }
}
