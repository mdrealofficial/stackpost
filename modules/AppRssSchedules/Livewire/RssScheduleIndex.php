<?php

namespace Modules\AppRssSchedules\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Services\RssScheduleService;
use Modules\AppRssSchedules\Support\InteractsWithRssSchedules;

#[Title('RSS Schedules')]
class RssScheduleIndex extends Component
{
    use InteractsWithRssSchedules;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = 'all';

    public ?string $activityKey = null;

    public function showActivity(string $scheduleKey): void
    {
        $schedule = RssSchedule::where('id_secure', $scheduleKey)->firstOrFail();
        abort_unless($this->canManage($schedule), 404);
        $this->activityKey = $scheduleKey;
    }

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
        $this->search = '';
        $this->status = 'all';
        $this->resetPage();
    }

    public function runSchedule(string $scheduleKey, RssScheduleService $rss): void
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $schedule = RssSchedule::query()->where('id_secure', $scheduleKey)->firstOrFail();
        abort_unless($this->canManage($schedule), 404);

        $result = $rss->processSchedule($schedule, true);

        session()->flash('status', ($result['message'] ?? '').' '.__('Created :queued, skipped :skipped.', [
            'queued' => $result['queued'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
        ]));
    }

    public function toggleSchedule(string $scheduleKey): void
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $schedule = RssSchedule::query()->where('id_secure', $scheduleKey)->firstOrFail();
        abort_unless($this->canManage($schedule), 404);

        if ($schedule->isDemo()) {
            session()->flash('status', __('Edit this sample with a real feed before enabling it.'));

            return;
        }

        $lock = Cache::lock('rss-schedule:'.$schedule->id, 600);
        if (! $lock->get()) {
            session()->flash('status', __('This schedule is processing. Try again after it finishes.'));

            return;
        }
        try {
            $schedule->refresh();
            $schedule->update(['status' => ! $schedule->status, 'changed' => now()->timestamp]);
        } finally {
            $lock->release();
        }

        session()->flash('status', $schedule->status ? __('RSS schedule enabled.') : __('RSS schedule paused.'));
    }

    public function deleteSchedule(string $scheduleKey): void
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $schedule = RssSchedule::query()->where('id_secure', $scheduleKey)->firstOrFail();
        abort_unless($this->canManage($schedule), 404);

        $lock = Cache::lock('rss-schedule:'.$schedule->id, 600);
        if (! $lock->get()) {
            session()->flash('status', __('This schedule is processing. Try deleting it after it finishes.'));

            return;
        }
        try {
            $schedule->delete();
        } finally {
            $lock->release();
        }

        session()->flash('status', __('RSS schedule deleted successfully.'));
    }

    public function statusBadge(RssSchedule $schedule): array
    {
        if ($schedule->isDemo()) {
            return ['label' => __('Sample'), 'tone' => 'neutral', 'reason' => __('Preview only. Edit with a real feed to use.')];
        }
        if ($schedule->end_at && $schedule->end_at < now()->timestamp) {
            return ['label' => __('Expired'), 'tone' => 'neutral', 'reason' => __('The schedule end date has passed.')];
        }
        if (! $schedule->status) {
            return ['label' => __('Paused'), 'tone' => 'neutral', 'reason' => __('Automatic processing is paused.')];
        }
        if (! $schedule->eligible_accounts_count) {
            return ['label' => __('Needs attention'), 'tone' => 'warning', 'reason' => __('No active destination channels.')];
        }
        if (data_get($schedule->settings, 'last_run.status') === 'error') {
            return ['label' => __('Needs attention'), 'tone' => 'warning', 'reason' => (string) data_get($schedule->settings, 'last_run.message')];
        }
        if (! $schedule->next_run_at || $schedule->next_run_at < now()->subMinutes(15)->timestamp) {
            return ['label' => __('Needs attention'), 'tone' => 'warning', 'reason' => __('No recent execution. Check the schedule and processing service.')];
        }

        return ['label' => __('Enabled'), 'tone' => 'success', 'reason' => __('Waiting for the next scheduled run.')];
    }

    public function render(): View
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $query = RssSchedule::query()
            ->where('user_id', $this->workspaceOwnerUserId())
            ->when($this->currentTeamId(), fn ($builder, $teamId) => $builder->where('team_id', $teamId))
            ->when(trim($this->search) !== '', function ($builder): void {
                $search = trim($this->search);
                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('feed_url', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($this->status === 'sample', fn ($builder) => $builder->where('settings->source', 'admin-faker'))
            ->when(in_array($this->status, ['1', '0', 'expired'], true), function ($builder): void {
                $builder->where(fn ($q) => $q->whereNull('settings->source')->orWhere('settings->source', '!=', 'admin-faker'));
                if ($this->status === 'expired') {
                    $builder->where('end_at', '<', now()->timestamp);
                } else {
                    $builder->where('status', $this->status === '1')->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()->timestamp));
                }
            })
            ->orderByRaw("CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(settings, '$.source')) = 'admin-faker' THEN 1 ELSE 0 END")
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $schedules = $query->paginate(12);
        $scheduleIds = $schedules->getCollection()->pluck('id')->all();
        $activeAccountIds = SocialAccount::where('created_by_user_id', $this->workspaceOwnerUserId())->where('is_active', true)->pluck('id')->all();
        $schedules->getCollection()->each(fn ($schedule) => $schedule->setAttribute('eligible_accounts_count', count(array_intersect((array) $schedule->account_ids, $activeAccountIds))));
        $activity = $this->activityKey ? RssSchedule::where('id_secure', $this->activityKey)->first() : null;
        if ($activity) {
            abort_unless($this->canManage($activity), 404);
        }

        return view('apprssschedules::livewire.index', [
            'schedules' => $schedules,
            'summary' => $this->statusSummary(),
            'queuedCounts' => $this->queuedCountsFor($scheduleIds),
            'publishingCounts' => $this->publishingCountsFor($scheduleIds),
            'aiRewriteUsage' => $this->aiRewriteUsageFor($scheduleIds),
            'aiRewriteCreditPreview' => $this->aiRewriteCreditPreview(),
            'activity' => $activity,
            'activityRows' => $activity ? $activity->history()->with('publishingPost:id,status,data')->latest('queued_at')->limit(30)->get() : collect(),
            'statusOptions' => [
                ['value' => 'all', 'label' => __('All')],
                ['value' => '1', 'label' => __('Enabled')],
                ['value' => '0', 'label' => __('Paused')],
                ['value' => 'expired', 'label' => __('Expired')],
                ['value' => 'sample', 'label' => __('Samples')],
            ],
            'timezone' => $this->currentTimezone(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('RSS Schedules'),
        ]);
    }
}
