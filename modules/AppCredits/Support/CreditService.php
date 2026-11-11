<?php

namespace Modules\AppCredits\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\AdminUser\Models\User;
use Modules\AppCredits\Models\CreditReservation;
use Modules\AppCredits\Models\CreditUsageLog;
use RuntimeException;

class CreditService
{
    public function __construct(
        protected CreditSettings $settings,
        protected CreditTopupService $topups,
    ) {}

    public function summary(?User $user, ?int $excludeReservationId = null): array
    {
        if (! $user) {
            return [
                'limit' => null,
                'used' => 0,
                'remaining' => null,
                'unlimited' => true,
                'started_at' => null,
                'expires_at' => null,
                'plan_limit' => null,
                'plan_used' => 0,
                'plan_remaining' => null,
                'topup_remaining' => 0,
                'total_remaining' => null,
                'gross_remaining' => null,
                'reserved' => 0,
                'low_balance' => false,
            ];
        }

        $limit = $this->planLimit($user);
        $startedAt = $this->startedAt($user);
        $used = $this->used($user);
        $unlimited = ! $user->plan || $limit === -1;
        $topupRemaining = $unlimited ? 0 : $this->topups->remaining($user);
        $planUsed = $unlimited ? 0 : $this->planUsed($user, $used);
        $planRemaining = $unlimited ? null : max(0, $limit - $planUsed);
        $grossRemaining = $unlimited ? null : ($planRemaining + $topupRemaining);
        $reserved = $unlimited ? 0 : $this->activeReservationAmount($user, $excludeReservationId);
        $availableRemaining = $unlimited ? null : max(0, (int) $grossRemaining - $reserved);

        return [
            'limit' => $unlimited ? null : max(0, $limit),
            'used' => $used,
            'remaining' => $availableRemaining,
            'unlimited' => $unlimited,
            'started_at' => $startedAt,
            'expires_at' => $user->plan_expires_at,
            'plan_limit' => $unlimited ? null : max(0, $limit),
            'plan_used' => $planUsed,
            'plan_remaining' => $planRemaining,
            'topup_remaining' => $topupRemaining,
            'total_remaining' => $availableRemaining,
            'gross_remaining' => $grossRemaining,
            'reserved' => $reserved,
            'low_balance' => ! $unlimited && $availableRemaining !== null && $availableRemaining <= $this->settings->lowBalanceThreshold(),
        ];
    }

    public function planLimit(?User $user, int $default = -1): int
    {
        if (! $user) {
            return $default;
        }

        $value = $user->planLimit('credits_usage_limit', $default);

        return is_numeric($value) ? (int) round((float) $value) : $default;
    }

    public function used(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) CreditUsageLog::query()
            ->where('user_id', $user->id)
            ->when($user->plan_id, fn ($query) => $query->where('plan_id', $user->plan_id))
            ->when($this->startedAt($user), fn ($query, $startedAt) => $query->where('created_at', '>=', $startedAt))
            ->sum('amount');
    }

    public function canConsume(?User $user, string $actionKey, int $quantity = 1, ?int $unitCost = null): bool
    {
        $quantity = max(1, $quantity);
        $summary = $this->summary($user);

        if ($summary['unlimited']) {
            return true;
        }

        $cost = ($unitCost ?? $this->costFor($user, $actionKey)) * $quantity;

        if ($this->settings->negativeBalanceAllowed() || ! $this->settings->actionBlockOnEmpty()) {
            return true;
        }

        return (int) ($summary['remaining'] ?? 0) >= $cost;
    }

    public function ensureCanConsume(?User $user, string $actionKey, int $quantity = 1, ?int $unitCost = null): void
    {
        if (! $this->canConsume($user, $actionKey, $quantity, $unitCost)) {
            throw new InsufficientCreditsException($this->settings->actionEmptyMessage());
        }
    }

    public function costFor(?User $user, string $actionKey, ?int $default = null): int
    {
        $definition = credit_action_registry()->get($actionKey);
        $planKey = $definition['plan_key'] ?? credit_action_registry()->planKeyFor($actionKey);
        $fallback = $default ?? (int) ($definition['default_cost'] ?? 0);

        if (! $user) {
            return max(0, $fallback);
        }

        $value = $user->planLimit($planKey, $fallback);

        return max(0, is_numeric($value) ? (int) round((float) $value) : $fallback);
    }

    public function consume(?User $user, string $actionKey, array $attributes = []): CreditUsageLog
    {
        if (! $user) {
            return $this->consumeLocked(null, $actionKey, $attributes);
        }

        return DB::transaction(function () use ($user, $actionKey, $attributes): CreditUsageLog {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->consumeLocked($lockedUser, $actionKey, $attributes);
        });
    }

    public function reserve(User $user, string $actionKey, string $idempotencyKey, array $attributes = []): CreditReservation
    {
        if (! Schema::hasTable('credit_reservations')) {
            throw new RuntimeException(__('Credit reservations are not ready. Run the latest database migrations.'));
        }

        $idempotencyKey = trim(substr($idempotencyKey, 0, 120));

        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('A credit reservation requires an idempotency key.');
        }

        return DB::transaction(function () use ($user, $actionKey, $idempotencyKey, $attributes): CreditReservation {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $quantity = max(1, (int) ($attributes['quantity'] ?? 1));
            $unitCost = max(0, (int) ($attributes['unit_cost'] ?? $this->costFor($lockedUser, $actionKey)));
            $amount = max(0, (int) ($attributes['amount'] ?? ($unitCost * $quantity)));
            $existing = CreditReservation::query()
                ->where('user_id', $lockedUser->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((string) $existing->action_key !== $actionKey || (int) $existing->amount !== $amount) {
                    throw new RuntimeException(__('This idempotency key was already used for a different credit action.'));
                }

                return $existing;
            }

            $summary = $this->summary($lockedUser);

            if (! $summary['unlimited']
                && $this->settings->actionBlockOnEmpty()
                && ! $this->settings->negativeBalanceAllowed()
                && $amount > (int) ($summary['remaining'] ?? 0)) {
                throw new InsufficientCreditsException($this->settings->actionEmptyMessage());
            }

            return CreditReservation::query()->create([
                'user_id' => (int) $lockedUser->id,
                'plan_id' => $lockedUser->plan_id,
                'action_key' => $actionKey,
                'idempotency_key' => $idempotencyKey,
                'status' => CreditReservation::STATUS_RESERVED,
                'unit_cost' => $unitCost,
                'quantity' => $quantity,
                'amount' => $amount,
                'metadata' => (array) ($attributes['metadata'] ?? []),
                'expires_at' => now()->addMinutes(max(2, min(60, (int) ($attributes['ttl_minutes'] ?? 15)))),
            ]);
        });
    }

    public function commitReservation(User $user, string $idempotencyKey, array $attributes = []): CreditUsageLog
    {
        return DB::transaction(function () use ($user, $idempotencyKey, $attributes): CreditUsageLog {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $reservation = CreditReservation::query()
                ->where('user_id', $lockedUser->id)
                ->where('idempotency_key', trim($idempotencyKey))
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status === CreditReservation::STATUS_COMMITTED && $reservation->usage_log_id) {
                return CreditUsageLog::query()->findOrFail($reservation->usage_log_id);
            }

            if ($reservation->status !== CreditReservation::STATUS_RESERVED) {
                throw new RuntimeException(__('This credit reservation can no longer be committed.'));
            }

            if ($reservation->expires_at && $reservation->expires_at->isPast()) {
                $reservation->update([
                    'status' => CreditReservation::STATUS_REFUNDED,
                    'refunded_at' => now(),
                    'metadata' => [...(array) $reservation->metadata, 'refund_reason' => 'expired'],
                ]);

                throw new RuntimeException(__('The credit reservation expired before the action completed.'));
            }

            $metadata = [
                ...(array) $reservation->metadata,
                ...(array) ($attributes['metadata'] ?? []),
                'reservation_id' => (int) $reservation->id,
                'idempotency_key' => (string) $reservation->idempotency_key,
            ];
            $usageLog = $this->consumeLocked(
                $lockedUser,
                (string) $reservation->action_key,
                [
                    'quantity' => (int) $reservation->quantity,
                    'unit_cost' => (int) $reservation->unit_cost,
                    'amount' => (int) $reservation->amount,
                    'feature' => $attributes['feature'] ?? null,
                    'metadata' => $metadata,
                ],
                (int) $reservation->id,
            );

            $reservation->update([
                'status' => CreditReservation::STATUS_COMMITTED,
                'usage_log_id' => (int) $usageLog->id,
                'committed_at' => now(),
                'metadata' => $metadata,
            ]);

            return $usageLog;
        });
    }

    public function refundReservation(User $user, string $idempotencyKey, string $reason = 'action_failed', array $metadata = []): CreditReservation
    {
        return DB::transaction(function () use ($user, $idempotencyKey, $reason, $metadata): CreditReservation {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $reservation = CreditReservation::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', trim($idempotencyKey))
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status === CreditReservation::STATUS_COMMITTED) {
                throw new RuntimeException(__('Committed credit usage cannot be refunded by releasing its reservation.'));
            }

            if ($reservation->status !== CreditReservation::STATUS_REFUNDED) {
                $reservation->update([
                    'status' => CreditReservation::STATUS_REFUNDED,
                    'refunded_at' => now(),
                    'metadata' => [
                        ...(array) $reservation->metadata,
                        ...$metadata,
                        'refund_reason' => trim($reason) ?: 'action_failed',
                    ],
                ]);
            }

            return $reservation->refresh();
        });
    }

    public function reservation(?User $user, string $idempotencyKey): ?CreditReservation
    {
        if (! $user || ! Schema::hasTable('credit_reservations')) {
            return null;
        }

        return CreditReservation::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', trim($idempotencyKey))
            ->first();
    }

    protected function consumeLocked(
        ?User $user,
        string $actionKey,
        array $attributes = [],
        ?int $excludeReservationId = null,
    ): CreditUsageLog {
        $quantity = max(1, (int) ($attributes['quantity'] ?? 1));
        $unitCost = max(0, (int) ($attributes['unit_cost'] ?? $this->costFor($user, $actionKey)));
        $amount = max(0, (int) ($attributes['amount'] ?? ($unitCost * $quantity)));
        $summary = $this->summary($user, $excludeReservationId);

        if (! $summary['unlimited']
            && $this->settings->actionBlockOnEmpty()
            && ! $this->settings->negativeBalanceAllowed()
            && $amount > (int) ($summary['remaining'] ?? 0)) {
            throw new InsufficientCreditsException($this->settings->actionEmptyMessage());
        }

        $before = $summary['unlimited'] ? null : (int) ($summary['remaining'] ?? 0);
        $after = $summary['unlimited'] ? null : ((int) ($summary['remaining'] ?? 0) - $amount);

        if (! $summary['unlimited'] && $user) {
            $topupRemaining = (int) ($summary['topup_remaining'] ?? 0);
            $planRemaining = (int) ($summary['plan_remaining'] ?? 0);
            $topupSpend = $this->settings->spendPriority() === 'topup_first'
                ? min($amount, $topupRemaining)
                : min(max(0, $amount - $planRemaining), $topupRemaining);

            if ($topupSpend > 0) {
                $this->topups->consume($user, $topupSpend, [
                    'action_key' => $actionKey,
                    'feature' => $attributes['feature'] ?? null,
                    'quantity' => $attributes['quantity'] ?? 1,
                    'metadata' => $attributes['metadata'] ?? [],
                ]);
            }
        }

        return CreditUsageLog::query()->create([
            'user_id' => $user?->id,
            'plan_id' => $user?->plan_id,
            'action_key' => $actionKey,
            'feature' => $attributes['feature'] ?? null,
            'amount' => $amount,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'credits_before' => $before,
            'credits_after' => $summary['unlimited'] ? null : max(0, $after),
            'is_unlimited' => (bool) $summary['unlimited'],
            'metadata' => $attributes['metadata'] ?? [],
        ]);
    }

    protected function activeReservationAmount(User $user, ?int $excludeReservationId = null): int
    {
        if (! Schema::hasTable('credit_reservations')) {
            return 0;
        }

        return (int) CreditReservation::query()
            ->where('user_id', $user->id)
            ->active()
            ->when($excludeReservationId, fn ($query, int $id) => $query->where('id', '!=', $id))
            ->sum('amount');
    }

    protected function planUsed(User $user, ?int $used = null): int
    {
        $used ??= $this->used($user);

        if ($this->settings->spendPriority() === 'topup_first') {
            return max(0, $used - $this->topups->totalConsumed($user));
        }

        return min($this->planLimit($user), $used);
    }

    protected function startedAt(User $user): ?CarbonInterface
    {
        return $user->plan_started_at ?: $user->created_at;
    }
}
