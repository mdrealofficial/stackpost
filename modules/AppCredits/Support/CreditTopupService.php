<?php

namespace Modules\AppCredits\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminUser\Models\User;
use Modules\AppCredits\Models\CreditPack;
use Modules\AppCredits\Models\CreditTopupLedger;

class CreditTopupService
{
    public function __construct(
        protected CreditSettings $settings,
    ) {}

    public function remaining(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) $this->baseAvailableLots($user)->sum('remaining');
    }

    public function availablePacksFor(?User $user): Collection
    {
        if (! $this->canUserBuyTopup($user)) {
            return new Collection();
        }

        return CreditPack::query()
            ->where('status', true)
            ->orderByDesc('featured')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function canUserBuyTopup(?User $user): bool
    {
        if (! $user || ! $this->settings->topupEnabled() || ! $this->settings->packEnabled()) {
            return false;
        }

        if ($this->settings->topupRequiresActivePlan() && ! $user->hasActivePlan()) {
            return false;
        }

        if (! $this->settings->trialUserCanBuyExtra() && $user->isInPlanTrial()) {
            return false;
        }

        if (! $this->settings->freePlanUserCanBuyExtra() && $user->plan?->free_plan) {
            return false;
        }

        return true;
    }

    public function grantPack(User $user, CreditPack $pack, ?PaymentHistory $paymentHistory = null, array $meta = []): CreditTopupLedger
    {
        $expiresAt = $this->settings->packExpiryDays() > 0 ? now()->addDays($this->settings->packExpiryDays()) : null;

        return CreditTopupLedger::query()->create([
            'user_id' => $user->id,
            'credit_pack_id' => $pack->id,
            'payment_history_id' => $paymentHistory?->id,
            'type' => 'purchase',
            'amount' => (int) $pack->credits,
            'remaining' => (int) $pack->credits,
            'expires_at' => $expiresAt,
            'metadata' => $meta,
        ]);
    }

    public function consume(User $user, int $amount, array $meta = []): int
    {
        $amount = max(0, $amount);
        if ($amount === 0) {
            return 0;
        }

        $consumed = 0;

        DB::transaction(function () use ($user, $amount, $meta, &$consumed): void {
            $remainingToConsume = $amount;
            $lots = $this->baseAvailableLots($user)->lockForUpdate()->get();

            foreach ($lots as $lot) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                $take = min((int) $lot->remaining, $remainingToConsume);
                if ($take <= 0) {
                    continue;
                }

                $lot->forceFill(['remaining' => max(0, (int) $lot->remaining - $take)])->save();

                CreditTopupLedger::query()->create([
                    'user_id' => $user->id,
                    'credit_pack_id' => $lot->credit_pack_id,
                    'payment_history_id' => null,
                    'type' => 'consume',
                    'amount' => -$take,
                    'remaining' => 0,
                    'expires_at' => null,
                    'metadata' => array_filter([
                        'source_lot_id' => $lot->id,
                        'action_key' => $meta['action_key'] ?? null,
                        'feature' => $meta['feature'] ?? null,
                        'context' => $meta,
                    ]),
                ]);

                $remainingToConsume -= $take;
                $consumed += $take;
            }
        });

        return $consumed;
    }

    public function totalConsumed(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return abs((int) CreditTopupLedger::query()
            ->where('user_id', $user->id)
            ->where('type', 'consume')
            ->sum('amount'));
    }

    protected function baseAvailableLots(User $user)
    {
        return CreditTopupLedger::query()
            ->where('user_id', $user->id)
            ->whereIn('type', ['purchase', 'adjustment'])
            ->where('remaining', '>', 0)
            ->when(! $this->settings->packCarryOver(), function ($query) use ($user): void {
                $startedAt = $user->plan_started_at ?: $user->created_at;

                if ($startedAt) {
                    $query->where('created_at', '>=', $startedAt);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('expires_at')
            ->orderBy('id');
    }
}
