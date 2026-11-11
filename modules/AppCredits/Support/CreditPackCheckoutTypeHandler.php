<?php

namespace Modules\AppCredits\Support;

use Illuminate\Support\Str;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminUser\Models\User;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppCredits\Models\CreditPack;
use Modules\AppPayments\Contracts\PaymentCheckoutTypeHandler;
use Modules\AppPayments\Support\PaymentCallbackResult;
use Modules\AppPayments\Support\PaymentCheckout;

class CreditPackCheckoutTypeHandler implements PaymentCheckoutTypeHandler
{
    public function __construct(
        protected AffiliateService $affiliate,
        protected CreditTopupService $topups,
    ) {}

    public function finalize(PaymentCheckout $checkout, PaymentCallbackResult $result): PaymentHistory
    {
        $user = User::query()->findOrFail($checkout->userId);
        $transactionId = $result->transactionId ?: Str::upper(Str::random(20));
        $amount = $result->amount ?? $checkout->amount;
        $currency = strtoupper($result->currency ?: $checkout->currency);
        $now = time();

        $paymentHistory = PaymentHistory::query()->firstOrCreate(
            ['transaction_id' => $transactionId],
            [
                'id_secure' => Str::random(32),
                'uid' => $user->id,
                'plan_id' => null,
                'from' => $checkout->gateway,
                'currency' => $currency,
                'by' => $checkout->gatewayType,
                'amount' => $amount,
                'status' => 1,
                'changed' => $now,
                'created' => $now,
                'meta' => array_filter([
                    'checkout' => $checkout->toArray(),
                    'gateway_meta' => $result->meta,
                    'message' => $result->message,
                    'kind' => 'credit_pack',
                ]),
            ]
        );

        if (($paymentHistory->meta['kind'] ?? null) === 'credit_pack' && ! data_get($paymentHistory->meta, 'topup_granted')) {
            $pack = CreditPack::query()->findOrFail((int) ($checkout->meta['credit_pack_id'] ?? 0));
            $this->topups->grantPack($user, $pack, $paymentHistory, [
                'checkout' => $checkout->toArray(),
                'gateway_meta' => $result->meta,
            ]);

            $meta = $paymentHistory->meta ?? [];
            $meta['topup_granted'] = true;
            $paymentHistory->forceFill(['meta' => $meta])->save();
        }

        $this->affiliate->applyCommissionFromPaymentHistory($paymentHistory);

        return $paymentHistory;
    }

    public function retryUrl(PaymentCheckout $checkout): string
    {
        return route('payment.credits', $checkout->meta['credit_pack_slug'] ?? ($checkout->meta['credit_pack_id'] ?? ''));
    }
}
