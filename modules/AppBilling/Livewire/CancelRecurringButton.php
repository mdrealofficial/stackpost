<?php

namespace Modules\AppBilling\Livewire;

use Livewire\Component;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AppPayments\Support\PaymentManager;
use Modules\AppPayments\Support\PaymentNotificationService;

class CancelRecurringButton extends Component
{
    public int $subscriptionId;

    protected PaymentManager $payments;

    protected PaymentNotificationService $notifications;

    public function boot(PaymentManager $payments, PaymentNotificationService $notifications): void
    {
        $this->payments = $payments;
        $this->notifications = $notifications;
    }

    public function cancel(): void
    {
        $subscription = PaymentSubscription::query()->findOrFail($this->subscriptionId);

        abort_unless((int) $subscription->uid === (int) auth()->id(), 404);

        if (! $subscription->canBeCancelledByUser()) {
            $this->dispatch('app-toast', type: 'error', message: __('This subscription cannot be cancelled from the portal.'));

            return;
        }

        try {
            $cancelled = (bool) $this->payments->call((string) $subscription->service, 'cancel_recurring', [
                'subscriptionId' => (string) $subscription->subscription_id,
                'reason' => 'Cancelled by the customer from the billing portal.',
                'atPeriodEnd' => false,
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('app-toast', type: 'error', message: $e->getMessage());

            return;
        }

        if (! $cancelled) {
            $this->dispatch('app-toast', type: 'error', message: __('The recurring subscription could not be cancelled at the payment gateway.'));

            return;
        }

        $subscription->forceFill([
            'status' => 2,
            'changed' => time(),
        ])->save();

        if (auth()->user()) {
            $this->notifications->notify(
                auth()->user(),
                'cancel',
                $this->notifications->buildContext(
                    user: auth()->user(),
                    plan: $subscription->plan,
                    subscription: $subscription,
                    extra: [
                        'gateway' => (string) $subscription->service,
                        'status' => 'cancelled',
                        'message' => __('Your recurring subscription was cancelled successfully.'),
                    ],
                )
            );
        }

        $this->dispatch('app-toast', type: 'success', message: __('Recurring subscription cancelled successfully.'));
        $this->redirectRoute('portal.billing', navigate: true);
    }

    public function render()
    {
        return view('appbilling::livewire.cancel-recurring-button');
    }
}
