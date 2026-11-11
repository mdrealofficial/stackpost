<?php

namespace Modules\AppCredits\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\AppCredits\Models\CreditPack;
use Modules\AppCredits\Support\CreditTopupService;
use Modules\AppPayments\Support\PaymentCheckout;
use Modules\AppPayments\Support\PaymentCheckoutStore;
use Modules\AppPayments\Support\PaymentManager;

class CreditPackCheckoutPage extends Component
{
    protected PaymentManager $payments;

    protected PaymentCheckoutStore $checkoutStore;

    protected CreditTopupService $topups;

    public string $packSource = '';

    public function boot(
        PaymentManager $payments,
        PaymentCheckoutStore $checkoutStore,
        CreditTopupService $topups,
    ): void {
        $this->payments = $payments;
        $this->checkoutStore = $checkoutStore;
        $this->topups = $topups;
    }

    public function mount(string $pack)
    {
        $this->packSource = $pack;

        abort_unless($this->currentPackOrFail()->status, 404);
        abort_unless($this->topups->canUserBuyTopup(auth()->user()), 404);

        return null;
    }

    public function checkout(string $gateway)
    {
        $pack = $this->currentPackOrFail();
        $definition = $this->payments->definition($gateway);

        abort_if(in_array($definition->type, ['recurring', 'manual'], true), 422);

        $checkout = new PaymentCheckout(
            gateway: $definition->key,
            gatewayType: $definition->type,
            userId: (int) auth()->id(),
            planId: 0,
            amount: (float) $pack->price,
            currency: strtoupper((string) ($pack->currency ?: 'USD')),
            returnUrl: route('payment.success', ['gateway' => $definition->key]),
            cancelUrl: route('payment.cancel', ['gateway' => $definition->key]),
            meta: [
                'checkout_kind' => 'credit_pack',
                'credit_pack_id' => $pack->id,
                'credit_pack_slug' => $pack->slug,
                'credit_pack_name' => $pack->name,
                'credits' => (int) $pack->credits,
            ],
        );

        $this->checkoutStore->put($checkout);

        try {
            $result = $this->payments->gateway($definition->key)->start($checkout);
        } catch (\Throwable $e) {
            $this->checkoutStore->forget();
            $this->dispatch('app-toast', type: 'error', message: $e->getMessage());

            return null;
        }

        return redirect()->away($result->redirectUrl);
    }

    public function render(): View
    {
        $pack = $this->currentPackOrFail();

        $gateways = collect($this->payments->available())
            ->filter(fn ($definition) => ! in_array($definition->type, ['recurring', 'manual'], true))
            ->values()
            ->all();

        return view('appcredits::portal.checkout', [
            'pack' => $pack,
            'gateways' => $gateways,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Buy more credits'),
            'shellArea' => 'user',
        ]);
    }

    protected function currentPackOrFail(): CreditPack
    {
        return CreditPack::query()
            ->where(function ($query): void {
                $query->where('slug', $this->packSource)->orWhere('id', $this->packSource);
            })
            ->firstOrFail();
    }
}
