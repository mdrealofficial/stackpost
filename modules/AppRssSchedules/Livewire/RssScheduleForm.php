<?php

namespace Modules\AppRssSchedules\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Services\RssScheduleService;
use Modules\AppRssSchedules\Support\InteractsWithRssSchedules;

#[Title('RSS Schedule Form')]
class RssScheduleForm extends Component
{
    use InteractsWithRssSchedules;

    public ?RssSchedule $schedule = null;

    public string $feed_url = '';

    public string $name = '';

    public string $description = '';

    public array $account_ids = [];

    public string $campaign_id = '';

    public array $label_ids = [];

    public array $time_posts = ['09:00'];

    public array $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

    public string $start_date = '';

    public string $end_date = '';

    public bool $accept_caption = true;

    public bool $deny_link = false;

    public bool $url_shorten = false;

    public bool $enable_ai_rewrite = false;

    public string $ai_rewrite_tone = 'professional';

    public string $ai_rewrite_language = 'vi';

    public string $ai_rewrite_prompt = '';

    public int $ai_rewrite_max_per_run = 0;

    public int $ai_rewrite_monthly_credit_limit = 0;

    public string $referral_code = '';

    public string $include_keywords = '';

    public string $ignore_keywords = '';

    public bool $status = true;

    public bool $urlShortenerAvailable = false;

    public string $delivery_mode = 'draft';

    public array $feedPreview = [];

    public function previewFeed(RssScheduleService $rss): void
    {
        abort_unless($this->rssSchedulesEnabled(), 404);
        $this->resetErrorBag('feed_url');
        $this->feedPreview = [];
        $this->validate(['feed_url' => ['required', 'url:http,https', 'max:2000']]);
        try {
            $feed = $rss->validateFeed($this->feed_url);
            $settings = ['accept_caption' => $this->accept_caption, 'deny_link' => $this->deny_link, 'referral_code' => $this->referral_code, 'include_keywords' => $this->include_keywords, 'ignore_keywords' => $this->ignore_keywords];
            $this->feedPreview = ['title' => $feed['title'], 'count' => count($feed['items']), 'items' => array_map(fn ($item) => $rss->previewItem($item, $settings), array_slice($feed['items'], 0, 3)), 'url' => $this->feed_url, 'inputs' => ['feed_url' => $this->feed_url] + $settings];
            $this->dispatch('rss-preview-ready');
        } catch (\Throwable $exception) {
            $this->addError('feed_url', __('Could not read this feed. Check that it is a reachable RSS or Atom URL.'));
        }
    }

    public function mount(?RssSchedule $rssSchedule = null): void
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $this->urlShortenerAvailable = function_exists('url_shortening_enabled')
            ? url_shortening_enabled(auth()->user())
            : false;

        if (! $rssSchedule) {
            return;
        }

        abort_unless($this->canManage($rssSchedule), 404);

        $this->schedule = $rssSchedule;
        $this->fillFromSchedule($rssSchedule);
    }

    public function save(RssScheduleService $rss): mixed
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'feed_url' => ['required', 'url:http,https', 'max:2000'],
            'delivery_mode' => ['required', 'in:draft,automatic'],
            'description' => ['nullable', 'string', 'max:5000'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'campaign_id' => ['nullable', 'integer'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
            'time_posts' => ['required', 'array', 'min:1'],
            'time_posts.*' => ['nullable', 'string'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'accept_caption' => ['nullable', 'boolean'],
            'deny_link' => ['nullable', 'boolean'],
            'url_shorten' => ['nullable', 'boolean'],
            'enable_ai_rewrite' => ['nullable', 'boolean'],
            'ai_rewrite_tone' => ['nullable', 'string', 'max:50'],
            'ai_rewrite_language' => ['nullable', 'string', 'max:12'],
            'ai_rewrite_prompt' => ['nullable', 'string', 'max:2000'],
            'ai_rewrite_max_per_run' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ai_rewrite_monthly_credit_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'referral_code' => ['nullable', 'string', 'max:20'],
            'include_keywords' => ['nullable', 'string', 'max:1000'],
            'ignore_keywords' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'boolean'],
        ], [
            'account_ids.required' => __('Choose at least one destination channel.'),
            'account_ids.min' => __('Choose at least one destination channel.'),
            'weekdays.required' => __('Choose at least one day for this schedule.'),
            'time_posts.required' => __('Add at least one import time.'),
        ], [
            'feed_url' => __('RSS URL'),
            'account_ids' => __('destination channels'),
            'account_ids.*' => __('destination channel'),
            'time_posts' => __('import times'),
            'weekdays' => __('schedule days'),
            'start_date' => __('start date'),
            'end_date' => __('end date'),
            'include_keywords' => __('include keywords'),
            'ignore_keywords' => __('exclude keywords'),
            'referral_code' => __('link tracking code'),
        ]);

        $payload = $this->validatedPayload($validated, $rss);
        try {
            $feed = $rss->validateFeed($payload['feed_url']);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['feed_url' => __('Could not read this feed. Check that it is a reachable RSS or Atom URL.')]);
        }
        $payload['name'] = $payload['name'] !== '' ? $payload['name'] : ($feed['title'] ?: Str::limit($payload['feed_url'], 80));
        $payload['description'] = $payload['description'] !== '' ? $payload['description'] : ($feed['description'] ?? null);

        if ($this->schedule) {
            abort_unless($this->canManage($this->schedule), 404);

            $lock = Cache::lock('rss-schedule:'.$this->schedule->id, 600);
            if (! $lock->get()) {
                throw ValidationException::withMessages(['feed_url' => __('This schedule is processing. Try saving again after it finishes.')]);
            }
            try {
                $this->schedule->refresh();
                $settings = (array) $this->schedule->settings;
                if ($this->schedule->feed_url !== $payload['feed_url']) {
                    unset($settings['feed_cursor'], $settings['feed_backlog'], $settings['last_run']);
                }
                unset($settings['source']);
                $payload['settings'] = array_replace($settings, $payload['settings']);
                $payload['changed'] = now()->timestamp;
                $this->schedule->update($payload);
            } finally {
                $lock->release();
            }

            log_activity('portal.rss-schedules.update', 'Updated an RSS schedule.', [
                'subject_type' => RssSchedule::class,
                'subject_id' => $this->schedule->id,
                'metadata' => [
                    'schedule' => $this->schedule->id_secure,
                    'feed_url' => $this->schedule->feed_url,
                ],
            ]);

            session()->flash('status', __('RSS schedule updated successfully.'));
        } else {
            $schedule = RssSchedule::query()->create($payload + [
                'id_secure' => Str::random(32),
                'user_id' => $this->workspaceOwnerUserId(),
                'team_id' => $this->currentTeamId(),
                'created' => now()->timestamp,
                'changed' => now()->timestamp,
            ]);

            log_activity('portal.rss-schedules.create', 'Created an RSS schedule.', [
                'subject_type' => RssSchedule::class,
                'subject_id' => $schedule->id,
                'metadata' => [
                    'schedule' => $schedule->id_secure,
                    'feed_url' => $schedule->feed_url,
                ],
            ]);

            session()->flash('status', __('RSS schedule created successfully.'));
        }

        return redirect()->route('portal.rss-schedules.index');
    }

    public function render(): View
    {
        abort_unless($this->rssSchedulesEnabled(), 404);

        $accounts = $this->accessibleAccounts();
        [$channelOptions, $channelNetworks] = $this->channelOptionsAndNetworks($accounts);

        return view('apprssschedules::livewire.form', [
            'channelOptions' => $channelOptions,
            'channelNetworks' => $channelNetworks,
            'campaigns' => PublishingCampaign::query()->ownedBy($this->workspaceOwnerUserId())->orderBy('name')->get(['id', 'name', 'status']),
            'labels' => PublishingLabel::query()->ownedBy($this->workspaceOwnerUserId())->orderBy('name')->get(['id', 'name']),
            'isEditing' => (bool) $this->schedule,
            'toneOptions' => $this->toneOptions(),
            'aiRewriteCreditPreview' => $this->aiRewriteCreditPreview(),
            'timezone' => $this->currentTimezone(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->schedule ? __('Edit RSS Schedule') : __('Create RSS Schedule'),
        ]);
    }

    protected function fillFromSchedule(RssSchedule $schedule): void
    {
        $settings = is_array($schedule->settings) ? $schedule->settings : [];
        $timezone = $this->currentTimezone();

        $this->feed_url = (string) $schedule->feed_url;
        $this->name = (string) ($schedule->name ?? '');
        $this->description = (string) ($schedule->description ?? '');
        $this->account_ids = collect((array) $schedule->account_ids)->map(fn ($id) => (string) $id)->values()->all();
        $this->campaign_id = filled(data_get($settings, 'campaign_id')) ? (string) data_get($settings, 'campaign_id') : '';
        $this->label_ids = collect((array) data_get($settings, 'label_ids', []))->map(fn ($id) => (string) $id)->values()->all();
        $this->time_posts = collect((array) $schedule->time_posts)->map(fn ($slot) => (string) $slot)->filter()->values()->all();
        $this->weekdays = collect((array) $schedule->weekdays)->filter()->keys()->values()->all();
        $this->start_date = $schedule->start_at ? Carbon::createFromTimestamp((int) $schedule->start_at, $timezone)->toDateString() : '';
        $this->end_date = $schedule->end_at ? Carbon::createFromTimestamp((int) $schedule->end_at, $timezone)->toDateString() : '';
        $this->accept_caption = (bool) data_get($settings, 'accept_caption', true);
        $this->deny_link = (bool) data_get($settings, 'deny_link', false);
        $this->url_shorten = (bool) data_get($settings, 'url_shorten', false);
        $this->enable_ai_rewrite = (bool) data_get($settings, 'enable_ai_rewrite', false);
        $this->ai_rewrite_tone = (string) data_get($settings, 'ai_rewrite_tone', 'professional');
        $this->ai_rewrite_language = (string) data_get($settings, 'ai_rewrite_language', 'vi');
        $this->ai_rewrite_prompt = (string) data_get($settings, 'ai_rewrite_prompt', '');
        $this->ai_rewrite_max_per_run = max(0, (int) data_get($settings, 'ai_rewrite_max_per_run', 0));
        $this->ai_rewrite_monthly_credit_limit = max(0, (int) data_get($settings, 'ai_rewrite_monthly_credit_limit', 0));
        $this->referral_code = (string) data_get($settings, 'referral_code', '');
        $this->include_keywords = (string) data_get($settings, 'include_keywords', '');
        $this->ignore_keywords = (string) data_get($settings, 'ignore_keywords', '');
        $this->status = (bool) $schedule->status;
        $this->delivery_mode = $schedule->isDemo() ? 'draft' : (string) data_get($settings, 'delivery_mode', 'automatic');
    }

    protected function toneOptions(): array
    {
        return [
            ['value' => 'professional', 'label' => __('Professional')],
            ['value' => 'friendly', 'label' => __('Friendly')],
            ['value' => 'sales', 'label' => __('Sales')],
            ['value' => 'playful', 'label' => __('Playful')],
            ['value' => 'casual', 'label' => __('Casual')],
            ['value' => 'luxury', 'label' => __('Luxury')],
            ['value' => 'premium', 'label' => __('Premium')],
            ['value' => 'bold', 'label' => __('Bold')],
            ['value' => 'confident', 'label' => __('Confident')],
            ['value' => 'persuasive', 'label' => __('Persuasive')],
            ['value' => 'educational', 'label' => __('Educational')],
            ['value' => 'informative', 'label' => __('Informative')],
            ['value' => 'storytelling', 'label' => __('Storytelling')],
            ['value' => 'inspirational', 'label' => __('Inspirational')],
            ['value' => 'empathetic', 'label' => __('Empathetic')],
            ['value' => 'witty', 'label' => __('Witty')],
            ['value' => 'humorous', 'label' => __('Humorous')],
            ['value' => 'minimal', 'label' => __('Minimal')],
            ['value' => 'urgent', 'label' => __('Urgent')],
            ['value' => 'formal', 'label' => __('Formal')],
        ];
    }
}
