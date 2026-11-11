<?php

namespace Modules\AppAICampaignWizard\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Models\Team;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

#[Title('AI Campaign Wizard')]
class CampaignWizardIndex extends Component
{
    use AIStudioAccess;

    public string $campaignName = '';
    public string $campaignGoal = '';
    public string $offer = '';
    public string $audience = '';
    public string $startDate = '';
    public int $days = 7;
    public string $language = 'en';
    public string $tone = 'professional';
    public array $selectedAccountIds = [];
    public array $result = [];
    public int $draftsCreated = 0;

    public function mount(): void
    {
        abort_unless($this->campaignWizardAvailable(), 404);

        $this->startDate = now($this->currentTimezone())->toDateString();
        $this->days = 7;
        $this->language = (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en');
        $this->tone = (string) $this->aiStudioSetting('default_tone', $this->tone);
        $this->selectedAccountIds = $this->accounts()->pluck('id')->take(2)->map(fn ($id) => (string) $id)->all();
        $this->restoreLatestHistory();
    }

    public function generate(AiContentStudioService $studio): void
    {
        abort_unless($this->campaignWizardAvailable(), 404);

        $validated = $this->validate($this->rules());

        try {
            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_campaign_wizard');
            }

            $accounts = $this->selectedAccounts();
            $platforms = $accounts->pluck('provider_key')->map(fn ($value) => strtolower((string) $value))->filter()->unique()->values()->all();
            $start = Carbon::parse($validated['startDate'], $this->currentTimezone())->startOfDay();

            $this->result = $studio->planCampaign([
                'campaign_name' => $validated['campaignName'],
                'goal' => $validated['campaignGoal'],
                'offer' => $validated['offer'],
                'audience' => $validated['audience'],
                'days' => $validated['days'],
                'language' => $validated['language'],
                'tone' => $validated['tone'],
                'platforms' => $platforms,
                ...$this->aiStudioWorkspacePromptConfig(),
            ]);

            $this->result['timeline'] = collect((array) ($this->result['timeline'] ?? []))
                ->map(fn ($item) => [
                    ...$item,
                    'date' => $start->copy()->addDays((int) ($item['day'] ?? $item['date_offset'] ?? 0))->toDateString(),
                ])
                ->values()
                ->all();

            $studio->recordPromptHistory(
                auth()->user(),
                'campaign_wizard',
                $this->briefText($validated),
                [
                    'campaign_name' => $validated['campaignName'],
                    'goal' => $validated['campaignGoal'],
                    'offer' => $validated['offer'],
                    'audience' => $validated['audience'],
                    'start_date' => $validated['startDate'],
                    'days' => $validated['days'],
                    'language' => $validated['language'],
                    'tone' => $validated['tone'],
                    'account_ids' => $this->selectedAccountIds,
                    'platforms' => $platforms,
                ],
                $this->result,
                [
                    'title' => __('Campaign - :name', ['name' => $validated['campaignName']]),
                    'language' => $validated['language'],
                    'tone' => $validated['tone'],
                ],
            );

            if (($this->result['source'] ?? null) === 'ai' && function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_campaign_wizard', [
                    'feature' => 'ai-studio.campaign-wizard',
                    'metadata' => [
                        'days' => $validated['days'],
                        'platforms' => $platforms,
                    ],
                ]);
            }

            $this->draftsCreated = 0;
        } catch (Throwable $exception) {
            $this->addError('campaignGoal', $exception->getMessage());
        }
    }

    public function createDraftPosts(): void
    {
        abort_unless($this->campaignWizardAvailable(), 404);

        if ($this->result === [] || empty($this->result['timeline'])) {
            return;
        }

        $validated = $this->validate([
            'campaignName' => ['required', 'string', 'max:120'],
            'startDate' => ['required', 'date'],
            'selectedAccountIds' => ['required', 'array', 'min:1'],
            'selectedAccountIds.*' => ['integer'],
        ]);

        $accounts = $this->selectedAccounts();

        if ($accounts->isEmpty()) {
            $this->addError('selectedAccountIds', __('Select at least one connected channel.'));
            return;
        }

        $campaign = PublishingCampaign::query()->firstOrCreate(
            [
                'owner_user_id' => $this->workspaceOwnerUserId(),
                'slug' => $this->uniqueCampaignSlug($validated['campaignName']),
            ],
            [
                'name' => trim($validated['campaignName']),
                'status' => 'active',
                'starts_on' => $validated['startDate'],
                'ends_on' => Carbon::parse($validated['startDate'], $this->currentTimezone())->addDays(max(1, $this->days) - 1)->toDateString(),
                'description' => trim((string) ($this->result['positioning'] ?? $this->campaignGoal)),
                'notes' => __('Created from AI Campaign Wizard.'),
            ]
        );

        $now = now()->timestamp;
        $created = 0;
        $teamId = $this->currentTeamId();
        $timelineCount = count((array) ($this->result['timeline'] ?? []));
        $channelCount = $accounts->count();

        foreach ((array) ($this->result['timeline'] ?? []) as $index => $item) {
            foreach ($accounts as $account) {
                $caption = $this->captionForAccount($item, $account);

                if ($caption === '') {
                    continue;
                }

                PublishingPost::query()->create([
                    'id_secure' => Str::random(32),
                    'user_id' => $this->workspaceOwnerUserId(),
                    'team_id' => $teamId,
                    'campaign' => $campaign->id,
                    'labels' => [],
                    'account_id' => $account->id,
                    'social_network' => (string) $account->provider_key,
                    'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
                    'module' => (string) $account->provider_key,
                    'function' => 'post',
                    'api_type' => 1,
                    'type' => 'text',
                    'method' => 'basic',
                    'query_id' => null,
                    'data' => [
                        'title' => trim((string) Str::limit((string) ($item['theme'] ?? $caption), 46, '...')),
                        'caption' => $caption,
                        'media_type' => 'text',
                        'medias' => [],
                        'ai' => [
                            'module' => 'campaign_wizard',
                            'campaign_name' => $this->campaignName,
                            'timeline_index' => $index,
                        ],
                        'options' => [
                            'schedule_mode' => 'draft',
                            'timezone' => $this->currentTimezone(),
                            'campaign_id' => $campaign->id,
                            'campaign' => $campaign->name,
                            'asset_brief' => (string) ($item['asset_brief'] ?? ''),
                            'cta' => (string) ($item['cta'] ?? ''),
                        ],
                    ],
                    'time_post' => ! empty($item['date']) ? Carbon::parse((string) $item['date'], $this->currentTimezone())->setTime(9, 0)->timestamp : null,
                    'delay' => 0,
                    'repost_frequency' => 0,
                    'repost_until' => null,
                    'result' => null,
                    'tmp' => null,
                    'custom_data_1' => 'campaign-wizard',
                    'custom_data_2' => (string) ($item['day'] ?? $index),
                    'custom_data_3' => null,
                    'status' => PublishingPost::STATUS_DRAFT,
                    'changed' => $now,
                    'created' => $now,
                ]);

                $created++;
            }
        }

        $this->draftsCreated = $created;

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Draft posts created'),
            message: __(':posts campaign posts x :channels channels = :count draft posts added to Publishing Drafts.', [
                'posts' => $timelineCount,
                'channels' => $channelCount,
                'count' => $created,
            ])
        );
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->campaignName = (string) data_get($history->input_payload, 'campaign_name', $this->campaignName);
        $this->campaignGoal = (string) data_get($history->input_payload, 'goal', $this->campaignGoal);
        $this->offer = (string) data_get($history->input_payload, 'offer', $this->offer);
        $this->audience = (string) data_get($history->input_payload, 'audience', $this->audience);
        $this->startDate = (string) data_get($history->input_payload, 'start_date', $this->startDate);
        $this->days = (int) data_get($history->input_payload, 'days', $this->days);
        $this->language = (string) data_get($history->input_payload, 'language', $this->language);
        $this->tone = (string) data_get($history->input_payload, 'tone', $this->tone);
        $this->selectedAccountIds = collect((array) data_get($history->input_payload, 'account_ids', []))->map(fn ($id) => (string) $id)->all();
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
        $this->draftsCreated = 0;
    }

    public function render(): View
    {
        abort_unless($this->campaignWizardAvailable(), 404);

        return view('appaicampaignwizard::index', [
            'accounts' => $this->accounts(),
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_campaign_wizard'),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
            'toneOptions' => $this->toneOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Campaign Wizard'),
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function rules(): array
    {
        $languageCodes = collect(world_languages())->pluck('code')->map(fn ($code) => (string) $code)->all();

        return [
            'campaignName' => ['required', 'string', 'max:120'],
            'campaignGoal' => ['required', 'string', 'max:2000'],
            'offer' => ['nullable', 'string', 'max:1200'],
            'audience' => ['required', 'string', 'max:1200'],
            'startDate' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:3', 'max:31'],
            'language' => ['required', 'string', 'max:12', 'in:'.implode(',', $languageCodes)],
            'tone' => ['required', 'string', 'max:40'],
            'selectedAccountIds' => ['required', 'array', 'min:1'],
            'selectedAccountIds.*' => ['integer'],
        ];
    }

    protected function accounts(): Collection
    {
        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where('is_active', true)
            ->orderBy('provider_key')
            ->orderBy('display_name')
            ->get();
    }

    protected function selectedAccounts(): Collection
    {
        $ids = collect($this->selectedAccountIds)->map(fn ($id) => (int) $id)->filter()->unique()->all();

        return $this->accounts()->whereIn('id', $ids)->values();
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'campaign_wizard');
    }

    protected function currentTeamId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (method_exists(TeamWorkspaceAccess::class, 'activeTeam')) {
            $team = TeamWorkspaceAccess::activeTeam($user);

            if ($team) {
                return (int) $team->id;
            }
        }

        return Team::query()->where('owner_user_id', $user->id)->value('id')
            ?: $user->teams()->value('teams.id');
    }

    protected function campaignWizardAvailable(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'ai_campaign_wizard')
            && $this->aiStudioFeatureEnabled('ai_studio_campaign_wizard');
    }

    protected function briefText(array $payload): string
    {
        return trim(implode("\n\n", array_filter([
            'Campaign: '.$payload['campaignName'],
            'Goal: '.$payload['campaignGoal'],
            'Offer: '.($payload['offer'] ?? ''),
            'Audience: '.$payload['audience'],
        ])));
    }

    protected function captionForAccount(array $item, SocialAccount $account): string
    {
        $platform = strtolower((string) $account->provider_key);
        $captions = (array) ($item['captions'] ?? []);
        $caption = (string) ($captions[$platform] ?? $captions['default'] ?? $item['caption'] ?? '');

        return trim($caption);
    }

    protected function uniqueCampaignSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $index = 2;

        while (PublishingCampaign::query()->ownedBy($this->workspaceOwnerUserId())->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    protected function restoreLatestHistory(): void
    {
        $history = $this->promptHistoryQuery()->latest('id')->first();

        if ($history) {
            $this->loadPromptHistory((int) $history->id);
        }
    }

    protected function toneOptions(): array
    {
        return [
            ['value' => 'professional', 'label' => __('Professional')],
            ['value' => 'friendly', 'label' => __('Friendly')],
            ['value' => 'sales', 'label' => __('Sales')],
            ['value' => 'educational', 'label' => __('Educational')],
            ['value' => 'bold', 'label' => __('Bold')],
            ['value' => 'casual', 'label' => __('Casual')],
        ];
    }
}
