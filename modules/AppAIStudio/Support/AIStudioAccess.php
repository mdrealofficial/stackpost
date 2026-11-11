<?php

namespace Modules\AppAIStudio\Support;

use Modules\AppAIStudio\Models\AIStudioUserSetting;
use Modules\AppAIStudio\Models\AIStudioWorkspaceSetting;
use Modules\AdminUser\Models\User;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

trait AIStudioAccess
{
    protected array $aiStudioRuntimeCache = [];

    protected function aiStudioCreditPreview(string $actionKey, ?int $unitCost = null, int $quantity = 1): array
    {
        $cacheKey = 'credit_preview:'.$actionKey.':'.($unitCost === null ? 'auto' : (string) $unitCost).':'.max(1, $quantity);

        if (array_key_exists($cacheKey, $this->aiStudioRuntimeCache)) {
            return $this->aiStudioRuntimeCache[$cacheKey];
        }

        $planOwner = $this->aiStudioPlanOwner();
        $quantity = max(1, $quantity);
        $summary = function_exists('credit_summary')
            ? credit_summary($planOwner)
            : ['remaining' => null, 'unlimited' => true];

        $cost = function_exists('credit_service')
            ? max(0, (int) ($unitCost ?? credit_service()->costFor($planOwner, $actionKey)))
            : max(0, (int) ($unitCost ?? 0));

        $amount = max(0, $cost * $quantity);
        $remaining = $summary['remaining'] ?? null;
        $unlimited = (bool) ($summary['unlimited'] ?? true);

        return $this->aiStudioRuntimeCache[$cacheKey] = [
            'action_key' => $actionKey,
            'unit_cost' => $cost,
            'quantity' => $quantity,
            'amount' => $amount,
            'remaining' => $remaining,
            'unlimited' => $unlimited,
            'enough' => $unlimited || $remaining === null || (int) $remaining >= $amount,
        ];
    }

    protected function aiStudioEnabled(): bool
    {
        if (array_key_exists('enabled', $this->aiStudioRuntimeCache)) {
            return (bool) $this->aiStudioRuntimeCache['enabled'];
        }

        $planOwner = $this->aiStudioPlanOwner();

        if (! $this->aiStudioWorkspaceAvailable()) {
            return $this->aiStudioRuntimeCache['enabled'] = false;
        }

        return $this->aiStudioRuntimeCache['enabled'] = (bool) ($planOwner?->canUsePlanFeature('ai_studio') ?? false);
    }

    protected function aiStudioFeatureEnabled(string $featureKey): bool
    {
        $cacheKey = 'feature:'.$featureKey;

        if (array_key_exists($cacheKey, $this->aiStudioRuntimeCache)) {
            return (bool) $this->aiStudioRuntimeCache[$cacheKey];
        }

        $planOwner = $this->aiStudioPlanOwner();

        if (! $this->aiStudioEnabled()) {
            return $this->aiStudioRuntimeCache[$cacheKey] = false;
        }

        return $this->aiStudioRuntimeCache[$cacheKey] = AIStudioPlanFeature::enabled($planOwner, $featureKey);
    }

    protected function aiStudioPlanOwner(): ?User
    {
        if (array_key_exists('plan_owner', $this->aiStudioRuntimeCache)) {
            return $this->aiStudioRuntimeCache['plan_owner'];
        }

        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);

        return $this->aiStudioRuntimeCache['plan_owner'] = ($team?->owner ?: $user);
    }

    protected function aiStudioWorkspaceAvailable(): bool
    {
        if (array_key_exists('workspace_available', $this->aiStudioRuntimeCache)) {
            return (bool) $this->aiStudioRuntimeCache['workspace_available'];
        }

        return $this->aiStudioRuntimeCache['workspace_available'] = TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'publishing');
    }

    protected function workspaceOwnerUserId(): int
    {
        if (array_key_exists('workspace_owner_user_id', $this->aiStudioRuntimeCache)) {
            return (int) $this->aiStudioRuntimeCache['workspace_owner_user_id'];
        }

        return $this->aiStudioRuntimeCache['workspace_owner_user_id'] = TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function currentTimezone(): string
    {
        return (string) (auth()->user()?->timezone ?: config('app.timezone'));
    }

    protected function aiStudioUserSettings(): array
    {
        $userId = (int) auth()->id();

        if ($userId <= 0) {
            return [];
        }

        $cacheKey = 'user_settings:'.$userId;

        if (array_key_exists($cacheKey, $this->aiStudioRuntimeCache)) {
            return (array) $this->aiStudioRuntimeCache[$cacheKey];
        }

        return $this->aiStudioRuntimeCache[$cacheKey] = (array) (AIStudioUserSetting::query()->forUser($userId)->value('settings') ?? []);
    }

    protected function aiStudioWorkspaceSettings(): array
    {
        $user = auth()->user();
        $ownerUserId = $this->workspaceOwnerUserId();

        if ($ownerUserId <= 0) {
            return [];
        }

        $teamId = TeamWorkspaceAccess::activeTeam($user)?->id;
        $cacheKey = 'workspace_settings:'.$ownerUserId.':'.((int) ($teamId ?? 0));

        if (array_key_exists($cacheKey, $this->aiStudioRuntimeCache)) {
            return (array) $this->aiStudioRuntimeCache[$cacheKey];
        }

        return $this->aiStudioRuntimeCache[$cacheKey] = (array) AIStudioWorkspaceSetting::query()
            ->ownedBy($ownerUserId)
            ->forTeam($teamId)
            ->value('settings') ?? [];
    }

    protected function aiStudioSetting(string $key, mixed $default = null): mixed
    {
        $workspace = $this->aiStudioWorkspaceSettings();
        $user = $this->aiStudioUserSettings();

        if (array_key_exists($key, $user) && $user[$key] !== null && $user[$key] !== '') {
            return $user[$key];
        }

        if (array_key_exists($key, $workspace) && $workspace[$key] !== null && $workspace[$key] !== '') {
            return $workspace[$key];
        }

        return $default;
    }

    protected function aiStudioWorkspacePromptConfig(): array
    {
        return array_filter([
            'brand_name' => $this->aiStudioSetting('brand_name'),
            'brand_description' => $this->aiStudioSetting('brand_description'),
            'brand_voice' => $this->aiStudioSetting('brand_voice'),
            'target_audience' => $this->aiStudioSetting('target_audience'),
            'brand_keywords' => $this->aiStudioSetting('brand_keywords'),
            'preferred_cta_style' => $this->aiStudioSetting('preferred_cta_style'),
            'banned_words' => $this->aiStudioSetting('banned_words'),
            'writing_examples' => $this->aiStudioSetting('writing_examples'),
        ], fn ($value) => filled($value));
    }
}
