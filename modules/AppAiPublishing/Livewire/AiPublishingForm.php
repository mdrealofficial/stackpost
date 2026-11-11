<?php

namespace Modules\AppAiPublishing\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAiPublishing\Models\AiPublishingPrompt;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppAiPublishing\Support\AiPublishingScheduler;
use Modules\AppAiPublishing\Support\AiPublishingTextGenerator;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use Modules\AppFiles\Support\OnlineMediaSearchService;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Publishing Form')]
class AiPublishingForm extends Component
{
    use AIStudioAccess;

    public ?AiPublishingRun $run = null;

    public string $promptSearch = '';

    public string $newPrompt = '';

    public string $runName = '';

    /** @var array<int, string> */
    public array $selectedPromptIds = [];

    /** @var array<int, string> */
    public array $account_ids = [];

    /** @var array<int, string> */
    public array $label_ids = [];

    /** @var array<int, string> */
    public array $weekdays = ['mon', 'wed', 'fri'];

    /** @var array<int, string> */
    public array $time_slots = ['14:30'];

    public string $campaign_id = '';

    public string $include_media = 'disable';

    public string $tone = 'professional';

    public string $language = 'en';

    public bool $include_hashtags = true;

    public bool $include_cta = true;

    public bool $allow_emoji = false;

    public string $start_at = '';

    public string $end_at = '';

    #[Locked]
    public string $timezone = '';

    public string $delivery_mode = 'draft';

    public bool $enabled = true;

    public int $run_budget = 10;

    public int $monthly_budget = 100;

    public array $preview = [];

    public function previewContent(AiPublishingTextGenerator $generator): void
    {
        abort_unless($this->aiPublishingEnabled(), 404);
        $this->preview = [];
        $this->resetErrorBag('preview');
        $prompt = AiPublishingPrompt::ownedBy($this->workspaceOwnerUserId())->where('is_active', true)->whereKey((int) ($this->selectedPromptIds[0] ?? 0))->first();
        if (! $prompt) {
            $this->addError('preview', __('Select a saved prompt first.'));

            return;
        }
        $owner = User::findOrFail($this->workspaceOwnerUserId());
        $lock = Cache::lock('ai-publishing:owner:'.$owner->id, 1800);
        if (! $lock->get()) {
            $this->addError('preview', __('Another generation is in progress. Try again shortly.'));

            return;
        }
        try {
            credit_service()->ensureCanConsume($owner, 'ai_publishing_generate_content');
            $config = ['tone' => $this->tone, 'language' => $this->language, 'hashtags' => $this->include_hashtags, 'cta' => $this->include_cta, 'emoji' => $this->allow_emoji, 'mode' => 'caption_only'];
            $result = $generator->generate($prompt->prompt_text, $config);
            consume_credits($owner, 'ai_publishing_generate_content', ['feature' => 'ai-publishing.preview']);
            $this->preview = ['caption' => $result['caption'], 'prompt' => $prompt->prompt_text, 'inputs' => ['selectedPromptIds' => $this->selectedPromptIds, 'tone' => $this->tone, 'language' => $this->language, 'include_hashtags' => $this->include_hashtags, 'include_cta' => $this->include_cta, 'allow_emoji' => $this->allow_emoji]];
        } catch (\Throwable $e) {
            $this->addError('preview', __('Could not generate a preview. Check your AI settings and credit balance, then try again.'));
        } finally {
            $lock->release();
        }
    }

    public function mount(?AiPublishingRun $run = null): void
    {
        abort_unless($this->aiPublishingEnabled(), 404);

        $this->timezone = $this->currentUserTimezone();
        $this->start_at = now($this->timezone)->format('Y-m-d');
        $this->end_at = now($this->timezone)->addWeeks(4)->format('Y-m-d');

        if (! $run) {
            return;
        }

        abort_unless($this->runBelongsToWorkspace($run), 404);
        $this->run = $run;
        $this->fillFromRun($run);
    }

    public function addPrompt(): void
    {
        $this->newPrompt = trim($this->newPrompt);
        abort_unless($this->aiPublishingEnabled(), 404);
        $validated = $this->validate([
            'newPrompt' => ['required', 'string', 'max:5000'],
        ]);

        $prompt = AiPublishingPrompt::query()->create([
            'owner_user_id' => $this->workspaceOwnerUserId(),
            'title' => Str::limit(trim($validated['newPrompt']), 80, '...'),
            'prompt_text' => trim($validated['newPrompt']),
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->selectedPromptIds[] = (string) $prompt->id;
        $this->selectedPromptIds = collect($this->selectedPromptIds)->unique()->values()->all();
        $this->newPrompt = '';
        $this->resetErrorBag('newPrompt');
    }

    public function deletePrompt(int $promptId): void
    {
        abort_unless($this->aiPublishingEnabled(), 404);
        if (AiPublishingRun::ownedBy($this->workspaceOwnerUserId())->get(['prompt_ids'])->contains(fn ($run) => in_array($promptId, array_map('intval', (array) $run->prompt_ids), true))) {
            $this->addError('selectedPromptIds', __('This prompt is used by a saved schedule. Remove it from that schedule before deleting it.'));

            return;
        }
        AiPublishingPrompt::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->whereKey($promptId)
            ->delete();

        $this->selectedPromptIds = collect($this->selectedPromptIds)
            ->reject(fn ($id) => (int) $id === $promptId)
            ->values()
            ->all();
    }

    public function save(): mixed
    {
        abort_unless($this->aiPublishingEnabled(), 404);
        $allowedMediaSources = array_keys($this->mediaSourceOptions());

        $validated = $this->validate([
            'delivery_mode' => ['required', 'in:draft,automatic'],
            'run_budget' => ['required', 'integer', 'min:0', 'max:1000000'],
            'monthly_budget' => ['required', 'integer', 'min:0', 'max:1000000'],
            'enabled' => ['boolean'],
            'runName' => ['nullable', 'string', 'max:180'],
            'selectedPromptIds' => ['required', 'array', 'min:1'],
            'selectedPromptIds.*' => ['integer'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'campaign_id' => ['nullable', 'integer'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
            'include_media' => ['required', Rule::in($allowedMediaSources)],
            'tone' => ['required', 'string', 'max:50'],
            'language' => ['required', 'string', 'max:12'],
            'time_slots' => ['required', 'array', 'min:1'],
            'time_slots.*' => ['required', 'date_format:H:i'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['required', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
        ], [
            'selectedPromptIds.required' => __('Choose at least one prompt.'),
            'account_ids.required' => __('Choose at least one destination channel.'),
        ]);

        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $promptIds = AiPublishingPrompt::query()
            ->ownedBy($workspaceOwnerUserId)
            ->where('is_active', true)
            ->whereIn('id', collect($validated['selectedPromptIds'])->map(fn ($id) => (int) $id)->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $promptIds = collect($validated['selectedPromptIds'])->map(fn ($id) => (int) $id)->unique()->filter(fn ($id) => in_array($id, $promptIds, true))->values()->all();
        if (count($promptIds) !== count(array_unique($validated['selectedPromptIds']))) {
            throw ValidationException::withMessages(['selectedPromptIds' => __('A selected prompt is no longer available. Choose your prompts again.')]);
        }
        $campaignId = filled($validated['campaign_id'] ?? null)
            ? PublishingCampaign::query()->ownedBy($workspaceOwnerUserId)->find((int) $validated['campaign_id'])?->id
            : null;
        $labelIds = PublishingLabel::query()
            ->ownedBy($workspaceOwnerUserId)
            ->whereIn('id', collect((array) ($validated['label_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $accountIds = $this->publishableAccountsQuery()
            ->whereIn('id', collect($validated['account_ids'])->map(fn ($id) => (int) $id)->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        if (count($accountIds) !== count(array_unique($validated['account_ids']))) {
            throw ValidationException::withMessages(['account_ids' => __('A selected channel is inactive or unavailable. Choose your channels again.')]);
        }
        $candidate = new AiPublishingRun(['schedule_config' => ['time_slots' => $validated['time_slots'], 'weekdays' => $validated['weekdays'], 'start_at' => $validated['start_at'], 'end_at' => $validated['end_at'], 'timezone' => $this->timezone]]);
        try {
            app(AiPublishingScheduler::class)->buildSlots($candidate->schedule_config, 1, $this->timezone);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['end_at' => __('Choose a date range with at least one upcoming time slot.')]);
        }
        $lock = $this->run ? Cache::lock('ai-publishing:run:'.$this->run->id, 1800) : null;
        if ($lock && ! $lock->get()) {
            throw ValidationException::withMessages(['runName' => __('This schedule is processing. Try saving after it finishes.')]);
        }
        try {
            if ($this->run) {
                $this->run->refresh();
                abort_unless($this->runBelongsToWorkspace($this->run), 404);
            }
            $resolvedTeamId = $this->resolveRunTeamId($accountIds);

            $run = $this->run ?: new AiPublishingRun([
                'owner_user_id' => $workspaceOwnerUserId,
                'team_id' => $resolvedTeamId,
                'workspace_owner_user_id' => $workspaceOwnerUserId,
            ]);

            $run->fill([
                'team_id' => $resolvedTeamId,
                'name' => trim($validated['runName']) ?: 'AI Publishing '.now()->format('Y-m-d H:i'),
                'campaign_id' => $campaignId,
                'label_ids' => $labelIds,
                'account_ids' => $accountIds,
                'prompt_ids' => $promptIds,
                'schedule_config' => [
                    'time_slots' => collect($validated['time_slots'])->unique()->sort()->values()->all(),
                    'weekdays' => array_values($validated['weekdays']),
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                    'timezone' => $this->timezone,
                ],
                'generation_config' => [
                    'delivery_mode' => $validated['delivery_mode'],
                    'run_budget' => $validated['run_budget'],
                    'monthly_budget' => $validated['monthly_budget'],
                    'mode' => ($validated['include_media'] ?? 'disable') === 'ai_image'
                        ? 'caption_content_image'
                        : 'caption_only',
                    'include_media' => $validated['include_media'] ?? 'disable',
                    'tone' => $validated['tone'],
                    'language' => $validated['language'],
                    'hashtags' => (bool) $this->include_hashtags,
                    'cta' => (bool) $this->include_cta,
                    'emoji' => (bool) $this->allow_emoji,
                ],
                'stats' => (array) ($run->stats ?? ['schedule_anchor_at' => now()->timestamp]),
                'status' => ! $this->enabled ? 'paused' : (($run->status === 'failed' || $run->occurrences()->whereIn('status', ['pending', 'processing', 'failed'])->exists()) ? 'failed' : 'queued'),
                'last_processed_at' => $run->last_processed_at,
            ])->save();

            $this->run = $run->fresh();
        } finally {
            if ($lock) {
                $lock->release();
            }
        }

        session()->flash('status', __('AI schedule saved. Existing posts and processing history were kept.'));

        return redirect()->route('portal.ai-publishing');
    }

    protected function resolveRunTeamId(array $accountIds): ?int
    {
        $activeTeamId = TeamWorkspaceAccess::activeTeam(auth()->user())?->id;

        if ((int) ($activeTeamId ?? 0) > 0) {
            return (int) $activeTeamId;
        }

        if ((int) ($this->run?->team_id ?? 0) > 0) {
            return (int) $this->run->team_id;
        }

        $ownerTeamId = Team::query()
            ->where('owner_user_id', $this->workspaceOwnerUserId())
            ->value('id');

        return (int) ($ownerTeamId ?: 0) > 0 ? (int) $ownerTeamId : null;
    }

    public function render(): View
    {
        abort_unless($this->aiPublishingEnabled(), 404);

        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $accountQuery = $this->publishableAccountsQuery();
        $accounts = (clone $accountQuery)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'provider_key', 'category', 'avatar_url']);
        $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');

        $bulkChannelOptions = $accounts
            ->map(function ($account) use ($channelProviderRegistry) {
                $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                $capabilityLabel = (string) ($account->category ?: __('Channel'));
                $providerTone = publishing_provider_tone((string) $account->provider_key);

                return [
                    'key' => (string) $account->id,
                    'label' => (string) ($account->display_name ?: ($account->username ? '@'.$account->username : strtoupper($account->provider_key))),
                    'subtitle' => trim($providerLabel.' '.str($capabilityLabel)->lower()),
                    'avatarUrl' => (string) ($account->avatar_url ?? ''),
                    'providerKey' => (string) $account->provider_key,
                    'providerLabel' => $providerLabel,
                    'providerIcon' => (string) data_get($provider, 'icon', ''),
                    'providerColor' => (string) data_get($provider, 'color', ''),
                    'providerToneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'providerToneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $bulkChannelNetworks = $channelProviderRegistry
            ->only($accounts->pluck('provider_key')->filter()->unique()->values()->all())
            ->map(function ($provider) {
                $providerKey = (string) ($provider['key'] ?? '');
                $providerTone = publishing_provider_tone($providerKey);

                return [
                    'key' => $providerKey,
                    'label' => (string) ($provider['label'] ?? ''),
                    'icon' => (string) ($provider['icon'] ?? ''),
                    'color' => (string) ($provider['color'] ?? ''),
                    'toneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'toneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->filter(fn ($provider) => $provider['key'] !== '' && $provider['label'] !== '')
            ->values()
            ->all();

        $prompts = AiPublishingPrompt::query()
            ->ownedBy($workspaceOwnerUserId)
            ->when($this->promptSearch !== '', fn ($query) => $query->where('prompt_text', 'like', '%'.trim($this->promptSearch).'%'))
            ->latest('id')
            ->get();

        return view('appaipublishing::livewire.form', [
            'prompts' => $prompts,
            'campaigns' => PublishingCampaign::query()->ownedBy($workspaceOwnerUserId)->orderBy('name')->get(['id', 'name', 'status']),
            'labels' => PublishingLabel::query()->ownedBy($workspaceOwnerUserId)->orderBy('name')->get(['id', 'name']),
            'bulkChannelOptions' => $bulkChannelOptions,
            'bulkChannelNetworks' => $bulkChannelNetworks,
            'weekdayOptions' => [
                ['key' => 'mon', 'label' => 'Mon'],
                ['key' => 'tue', 'label' => 'Tue'],
                ['key' => 'wed', 'label' => 'Wed'],
                ['key' => 'thu', 'label' => 'Thu'],
                ['key' => 'fri', 'label' => 'Fri'],
                ['key' => 'sat', 'label' => 'Sat'],
                ['key' => 'sun', 'label' => 'Sun'],
            ],
            'toneOptions' => [
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
            ],
            'mediaSourceGroups' => collect($this->mediaSourceGroups())
                ->map(fn ($options, $label) => [
                    'label' => (string) $label,
                    'options' => collect($options)
                        ->map(fn ($optionLabel, $value) => ['value' => (string) $value, 'label' => (string) $optionLabel])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'searchMediaOnlineEnabled' => app(FileManager::class)->searchMediaOnlineEnabled(auth()->user()),
            'hasOnlineMediaProviders' => $this->onlineMediaProviders() !== [],
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_generate_image'),
            'textCreditPreview' => $this->aiStudioCreditPreview('ai_publishing_generate_content'),
            'isEditing' => (bool) $this->run,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->run ? __('Edit AI Publishing') : __('Create AI Publishing'),
        ]);
    }

    protected function fillFromRun(AiPublishingRun $run): void
    {
        $this->delivery_mode = $run->isDemo() ? 'draft' : (string) data_get($run->generation_config, 'delivery_mode', 'automatic');
        $this->enabled = $run->status !== 'paused';
        $this->run_budget = (int) data_get($run->generation_config, 'run_budget', 0);
        $this->monthly_budget = (int) data_get($run->generation_config, 'monthly_budget', 0);
        $this->runName = (string) ($run->name ?? '');
        $this->selectedPromptIds = collect((array) ($run->prompt_ids ?? []))->map(fn ($id) => (string) $id)->values()->all();
        $this->account_ids = collect((array) ($run->account_ids ?? []))->map(fn ($id) => (string) $id)->values()->all();
        $this->label_ids = collect((array) ($run->label_ids ?? []))->map(fn ($id) => (string) $id)->values()->all();
        $this->campaign_id = filled($run->campaign_id) ? (string) $run->campaign_id : '';
        $this->time_slots = collect((array) data_get($run->schedule_config, 'time_slots', data_get($run->schedule_config, 'times', ['14:30'])))->map(fn ($slot) => (string) $slot)->filter()->values()->all();
        $this->weekdays = collect((array) data_get($run->schedule_config, 'weekdays', data_get($run->schedule_config, 'days', ['mon', 'wed', 'fri'])))->map(fn ($day) => (string) $day)->filter()->values()->all();
        $this->start_at = (string) data_get($run->schedule_config, 'start_at', $this->start_at);
        $this->end_at = (string) data_get($run->schedule_config, 'end_at', $this->end_at);
        $this->timezone = $this->currentUserTimezone();
        $savedIncludeMedia = (string) data_get($run->generation_config, 'include_media', '');
        $savedMode = (string) data_get($run->generation_config, 'mode', 'caption_only');
        $resolvedIncludeMedia = match (true) {
            $savedIncludeMedia !== '' => $savedIncludeMedia,
            $savedMode === 'caption_content_image' => 'ai_image',
            default => 'disable',
        };
        $this->include_media = array_key_exists($resolvedIncludeMedia, $this->mediaSourceOptions())
            ? $resolvedIncludeMedia
            : 'disable';
        $this->tone = (string) data_get($run->generation_config, 'tone', 'professional');
        $this->language = (string) data_get($run->generation_config, 'language', 'en');
        $this->include_hashtags = (bool) data_get($run->generation_config, 'hashtags', true);
        $this->include_cta = (bool) data_get($run->generation_config, 'cta', true);
        $this->allow_emoji = (bool) data_get($run->generation_config, 'emoji', false);
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function runBelongsToWorkspace(AiPublishingRun $run): bool
    {
        $ownerId = $this->workspaceOwnerUserId();
        $workspaceOwnerId = (int) ($run->workspace_owner_user_id ?? 0);

        if ($workspaceOwnerId > 0) {
            return $workspaceOwnerId === $ownerId;
        }

        return (int) $run->owner_user_id === $ownerId;
    }

    protected function currentUserTimezone(): string
    {
        return (string) (User::find($this->workspaceOwnerUserId())?->timezone ?: config('app.timezone'));
    }

    protected function mediaSourceOptions(): array
    {
        return collect($this->mediaSourceGroups())
            ->reduce(function (array $carry, array $group): array {
                foreach ($group as $value => $label) {
                    $carry[(string) $value] = (string) $label;
                }

                return $carry;
            }, []);
    }

    protected function mediaSourceGroups(): array
    {
        $groups = [
            __('Built-in') => [
                'disable' => __('Text only'),
                'ai_image' => __('AI image'),
            ],
        ];

        $onlineProviders = $this->onlineMediaProviders();

        if ($onlineProviders !== []) {
            $groups[__('Online sources')] = collect($onlineProviders)
                ->mapWithKeys(fn ($label, $value) => [(string) $value => (string) $label])
                ->all();
        }

        $folders = AppFile::query()
            ->ownedBy(auth()->user())
            ->where('is_folder', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($folders->isNotEmpty()) {
            $groups[__('Folders')] = $folders
                ->mapWithKeys(fn ($folder) => ['folder:'.$folder->id => (string) $folder->name])
                ->all();
        }

        return $groups;
    }

    protected function onlineMediaProviders(): array
    {
        if (! app(FileManager::class)->searchMediaOnlineEnabled(auth()->user())) {
            return [];
        }

        return app(OnlineMediaSearchService::class)->providers('all');
    }

    protected function aiPublishingEnabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        if (! TeamWorkspaceAccess::teamHasModule($team, 'ai_publishing')) {
            return false;
        }

        return ! $planOwner?->plan || $planOwner->hasPlanFeature('ai_publishing');
    }

    protected function publishableAccountsQuery()
    {
        $publishableCapabilityKeys = publishable_channel_capability_keys(auth()->user());

        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())->where('is_active', true)
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            });
    }
}
