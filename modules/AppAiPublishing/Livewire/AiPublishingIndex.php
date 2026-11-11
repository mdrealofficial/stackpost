<?php

namespace Modules\AppAiPublishing\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAiPublishing\Jobs\ProcessAiPublishingRunJob;
use Modules\AppAiPublishing\Models\AiPublishingOccurrence;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppAiPublishing\Support\AiPublishingScheduler;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Schedules')]
class AiPublishingIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public ?int $activityId = null;

    public string $actionMessage = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    protected function query()
    {
        abort_unless($this->enabled(), 404);
        $id = TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());

        return AiPublishingRun::where(fn ($q) => $q->where('workspace_owner_user_id', $id)->orWhere(fn ($q) => $q->whereNull('workspace_owner_user_id')->where('owner_user_id', $id)));
    }

    protected function enabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $owner = $team?->owner ?: $user;

        return TeamWorkspaceAccess::teamHasModule($team, 'ai_publishing') && (! $owner?->plan || $owner->hasPlanFeature('ai_publishing'));
    }

    protected function mutate(int $id, callable $action): void
    {
        $run = $this->query()->findOrFail($id);
        $lock = Cache::lock('ai-publishing:run:'.$id, 1800);
        if (! $lock->get()) {
            $this->actionMessage = __('This schedule is processing. Try again shortly.');

            return;
        }
        try {
            $run->refresh();
            $action($run);
        } finally {
            $lock->release();
        }
    }

    public function stopRun(int $id): void
    {
        $this->mutate($id, function ($run) {
            $run->update(['status' => 'paused']);
            $this->actionMessage = __('Schedule paused. Existing drafts and queued posts keep their current status.');
        });
    }

    public function startRun(int $id): void
    {
        $this->mutate($id, function ($run) {
            if ($run->isDemo() || $run->hasEnded()) {
                $this->actionMessage = __('Edit the source or date range before enabling this schedule.');

                return;
            }
            $run->update(['status' => $run->occurrences()->whereIn('status', ['failed', 'pending', 'processing'])->exists() ? 'failed' : 'queued']);
            $this->actionMessage = __('Schedule enabled. Existing drafts stay in Drafts.');
        });
    }

    public function runNow(int $id): void
    {
        $run = $this->query()->findOrFail($id);
        $result = app()->call([new ProcessAiPublishingRunJob($run->id, true), 'handle']);
        $this->actionMessage = $result['message'];
    }

    public function skipAttempt(int $id): void
    {
        $this->mutate($id, function ($run) {
            $attempt = $run->occurrences()->whereIn('status', ['failed', 'pending', 'processing'])->orderBy('slot_at')->first();
            if (! $attempt) {
                return;
            }
            DB::transaction(function () use ($run, $attempt) {
                $attempt->update(['status' => 'skipped', 'credits_reserved' => $attempt->credits_spent]);
                $stats = (array) $run->stats;
                $stats['last_slot_at'] = $attempt->slot_at;
                $stats['prompt_cursor'] = (int) ($stats['prompt_cursor'] ?? 0) + 1;
                $stats['last_error_message'] = null;
                $run->update(['stats' => $stats, 'status' => $run->status === 'paused' ? 'paused' : 'queued']);
            });
            $this->actionMessage = __('Attempt skipped. Existing posts and credit history were kept.');
        });
    }

    public function deleteRun(int $id): void
    {
        $this->mutate($id, function ($run) {
            $run->delete();
            $this->actionMessage = __('Schedule deleted. Existing posts were kept in Publishing.');
        });
        $this->activityId = null;
    }

    public function showActivity(int $id): void
    {
        $this->query()->findOrFail($id);
        $this->activityId = $id;
    }

    public function closeActivity(): void
    {
        $this->activityId = null;
    }

    public function render(): View
    {
        $all = $this->query()->latest('id')->get();
        $summary = $all->groupBy(fn ($r) => $r->displayStatus())->map->count();
        $filtered = $all->filter(fn ($r) => ($this->status === 'all' || $r->displayStatus() === $this->status) && ($this->search === '' || str_contains(mb_strtolower($r->name), mb_strtolower(trim($this->search)))))->sortBy(fn ($r) => $r->isDemo() ? 1 : 0)->values();
        $page = max(1, $this->getPage());
        $runs = new LengthAwarePaginator($filtered->forPage($page, 10)->values(), $filtered->count(), 10, $page, ['path' => request()->url()]);
        $ids = $runs->getCollection()->pluck('id');
        $postCounts = PublishingPost::where('custom_data_3', 'ai-publishing')->whereIn('custom_data_1', $ids->map(fn ($id) => (string) $id))->selectRaw('custom_data_1 as run_id,status,COUNT(*) as total')->groupBy('custom_data_1', 'status')->get()->groupBy('run_id');
        $totals = AiPublishingOccurrence::whereIn('run_id', $ids)->selectRaw('run_id,SUM(credits_spent) as credits,SUM(status = ?) as generated_count', ['success'])->groupBy('run_id')->get()->keyBy('run_id');
        $next = [];
        foreach ($runs as $run) {
            try {
                $next[$run->id] = app(AiPublishingScheduler::class)->nextForRun($run)?->format('d M, H:i');
            } catch (\Throwable) {
                $next[$run->id] = null;
            }
        }
        $activity = $this->activityId ? $this->query()->find($this->activityId) : null;

        return view('appaipublishing::livewire.index', [
            'runs' => $runs, 'summary' => $summary, 'total' => $all->count(), 'postCounts' => $postCounts, 'totals' => $totals, 'next' => $next,
            'activity' => $activity, 'attempts' => $activity ? $activity->occurrences()->latest('id')->limit(30)->get() : collect(),
        ])->layout(theme_view('layouts.app', 'app'), ['title' => __('AI Schedules')]);
    }
}
