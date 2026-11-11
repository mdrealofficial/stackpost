<?php

namespace Modules\AppPayments\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class UserPlanTransitionService
{
    public function preview(User $user, AdminPlan $targetPlan): array
    {
        $this->activateScheduledPlanIfDue($user);
        $user->refresh()->loadMissing('plan');

        $currentPlan = $this->activeCurrentPlan($user);

        if (! $currentPlan) {
            $usesTrialWindow = $this->shouldUseTrialWindow($user, $targetPlan);
            [$startedAt, $expiresAt] = $this->resolvePlanWindow($targetPlan, useTrialDays: $usesTrialWindow);

            return $this->normalizePayload($usesTrialWindow ? 'trial' : 'new', $startedAt, $expiresAt, 0, null, $currentPlan, $targetPlan);
        }

        if ((int) $currentPlan->id === (int) $targetPlan->id) {
            if ((int) $targetPlan->type === 3) {
                return $this->normalizePayload('renewal_lifetime', $user->plan_started_at, null, 0, null, $currentPlan, $targetPlan);
            }

            $baseStart = $user->plan_expires_at && $user->plan_expires_at->isFuture()
                ? $user->plan_expires_at->copy()
                : now();

            [, $expiresAt] = $this->resolvePlanWindow($targetPlan, $baseStart);

            return $this->normalizePayload('renewal', $user->plan_started_at, $expiresAt, 0, null, $currentPlan, $targetPlan);
        }

        if ((int) $currentPlan->type === 3) {
            [$startedAt, $expiresAt] = $this->resolvePlanWindow($targetPlan);

            return $this->normalizePayload('replace', $startedAt, $expiresAt, 0, null, $currentPlan, $targetPlan);
        }

        if ($this->isDowngrade($currentPlan, $targetPlan)) {
            return $this->normalizePayload('downgrade_scheduled', $user->plan_started_at, $user->plan_expires_at, 0, $user->plan_expires_at, $currentPlan, $targetPlan);
        }

        $carrySeconds = $this->remainingSeconds($user);
        [$startedAt, $expiresAt] = $this->resolvePlanWindow($targetPlan, now(), $carrySeconds);

        return $this->normalizePayload('upgrade', $startedAt, $expiresAt, $carrySeconds, null, $currentPlan, $targetPlan);
    }

    public function applyPurchasedPlan(User $user, AdminPlan $targetPlan): array
    {
        $decision = $this->preview($user, $targetPlan);

        switch ($decision['mode']) {
            case 'downgrade_scheduled':
                $user->forceFill([
                    'next_plan_id' => $targetPlan->id,
                ])->save();
                break;

            case 'renewal_lifetime':
                $user->forceFill([
                    'next_plan_id' => null,
                ])->save();
                break;

            case 'renewal':
                $user->forceFill([
                    'plan_id' => $targetPlan->id,
                    'plan_started_at' => $user->plan_started_at ?: now(),
                    'plan_expires_at' => $decision['expires_at'] ? Carbon::parse($decision['expires_at']) : null,
                    'next_plan_id' => null,
                ])->save();
                break;

            default:
                $user->forceFill([
                    'plan_id' => $targetPlan->id,
                    'plan_started_at' => $decision['started_at'] ? Carbon::parse($decision['started_at']) : now(),
                    'plan_expires_at' => $decision['expires_at'] ? Carbon::parse($decision['expires_at']) : null,
                    'next_plan_id' => null,
                ])->save();
                break;
        }

        $user->refresh()->loadMissing('plan');

        return $decision;
    }

    public function activateScheduledPlanIfDue(User $user): bool
    {
        if (! $user->next_plan_id) {
            return false;
        }

        if (! $user->plan_expires_at || $user->plan_expires_at->isFuture()) {
            return false;
        }

        $nextPlan = AdminPlan::query()->find($user->next_plan_id);

        if (! $nextPlan || ! $nextPlan->status) {
            $user->forceFill([
                'next_plan_id' => null,
            ])->save();

            return false;
        }

        [$startedAt, $expiresAt] = $this->resolvePlanWindow($nextPlan);

        $user->forceFill([
            'plan_id' => $nextPlan->id,
            'plan_started_at' => $startedAt,
            'plan_expires_at' => $expiresAt,
            'next_plan_id' => null,
        ])->save();

        return true;
    }

    public function activateDuePlans(): int
    {
        $count = 0;

        User::query()
            ->whereNotNull('next_plan_id')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    if ($this->activateScheduledPlanIfDue($user)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function resolvePlanWindow(AdminPlan $plan, ?CarbonInterface $startAt = null, int $carrySeconds = 0, bool $useTrialDays = false): array
    {
        $startedAt = Carbon::parse($startAt ?: now());
        $expiresAt = $useTrialDays && (int) $plan->trial_day > 0
            ? $startedAt->copy()->addDays((int) $plan->trial_day)
            : match ((int) $plan->type) {
                2 => $startedAt->copy()->addYear(),
                3 => null,
                default => $startedAt->copy()->addMonth(),
            };

        if ($expiresAt && $carrySeconds > 0) {
            $expiresAt->addSeconds($carrySeconds);
        }

        return [$startedAt, $expiresAt];
    }

    protected function activeCurrentPlan(User $user): ?AdminPlan
    {
        if (! $user->plan || ! $user->plan->status) {
            return null;
        }

        if ($user->plan_expires_at && $user->plan_expires_at->isPast()) {
            return null;
        }

        return $user->plan;
    }

    protected function remainingSeconds(User $user): int
    {
        if (! $user->plan_expires_at || ! $user->plan_expires_at->isFuture()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($user->plan_expires_at, false));
    }

    protected function isDowngrade(AdminPlan $currentPlan, AdminPlan $targetPlan): bool
    {
        return $this->planWeight($targetPlan) < $this->planWeight($currentPlan);
    }

    protected function shouldUseTrialWindow(User $user, AdminPlan $targetPlan): bool
    {
        if ((int) $targetPlan->trial_day <= 0 || $targetPlan->free_plan) {
            return false;
        }

        return ! PaymentHistory::query()
            ->where('uid', $user->id)
            ->exists();
    }

    protected function planWeight(AdminPlan $plan): int
    {
        $typeWeight = match ((int) $plan->type) {
            3 => 100000000,
            2 => 1000000,
            default => 0,
        };

        return $typeWeight + (int) round(((float) $plan->price) * 100);
    }

    protected function normalizePayload(
        string $mode,
        mixed $startedAt,
        mixed $expiresAt,
        int $carrySeconds,
        mixed $effectiveAt,
        ?AdminPlan $currentPlan,
        AdminPlan $targetPlan,
    ): array {
        return [
            'mode' => $mode,
            'carry_seconds' => $carrySeconds,
            'started_at' => $startedAt instanceof Carbon ? $startedAt->toIso8601String() : null,
            'expires_at' => $expiresAt instanceof Carbon ? $expiresAt->toIso8601String() : null,
            'effective_at' => $effectiveAt instanceof Carbon ? $effectiveAt->toIso8601String() : null,
            'current_plan_id' => $currentPlan?->id,
            'target_plan_id' => $targetPlan->id,
        ];
    }
}
