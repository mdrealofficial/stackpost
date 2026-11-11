<?php

namespace Modules\AppAIBestTime\Livewire;

use Illuminate\Contracts\View\View;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Best Time')]
class BestTimeIndex extends Component
{
    use AIStudioAccess;

    public array $selectedAccountIds = [];
    public array $result = [];

    public function suggest(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_best_time'), 404);

        $accountIds = collect($this->selectedAccountIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        try {
            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_best_time');
            }

            $this->result = $studio->bestTimeSuggestions($this->workspaceOwnerUserId(), $accountIds, $this->currentTimezone());

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_best_time', [
                    'feature' => 'ai-studio.best-time',
                    'metadata' => ['account_ids' => $accountIds],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('selectedAccountIds', $exception->getMessage());
        }
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_best_time'), 404);

        $accounts = TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'provider_key', 'category', 'avatar_url']);

        $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');

        $channelOptions = $accounts
            ->map(function ($account) use ($channelProviderRegistry) {
                $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                $capabilityLabel = (string) ($account->category ?: __('Channel'));
                $providerTone = publishing_provider_tone((string) $account->provider_key);

                return [
                    'key' => (string) $account->id,
                    'label' => (string) ($account->display_name ?: ($account->username ? '@'.$account->username : strtoupper((string) $account->provider_key))),
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

        $channelNetworks = $channelProviderRegistry
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

        return view('appaibesttime::index', [
            'accounts' => $accounts,
            'channelOptions' => $channelOptions,
            'channelNetworks' => $channelNetworks,
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_best_time'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Best Time'),
        ]);
    }
}
