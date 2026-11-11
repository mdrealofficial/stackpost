<?php

namespace Modules\AppPayments\Support;

use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Notifications\PaymentEventNotification;

class PaymentNotificationService
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function notify(User $user, string $event, array $context = []): void
    {
        if (! $this->enabled($event)) {
            return;
        }

        $subject = $this->render($this->subject($event), $user, $context);
        $message = $this->render($this->message($event), $user, $context);

        $user->notify(new PaymentEventNotification(
            subject: $subject,
            message: $message,
            actionUrl: route('portal.billing'),
            actionText: __('Open billing'),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'payment_notify_success_status' => '1',
            'payment_notify_success_subject' => 'Payment confirmed for :plan',
            'payment_notify_success_message' => "Your payment of :amount :currency via :gateway was confirmed.\n\nTransaction ID: :transaction_id.\nCurrent plan: :plan.",
            'payment_notify_failed_status' => '1',
            'payment_notify_failed_subject' => 'Payment failed for :plan',
            'payment_notify_failed_message' => "We could not confirm your payment via :gateway.\n\nReason: :message.\nPlan: :plan.",
            'payment_notify_review_status' => '1',
            'payment_notify_review_subject' => 'Payment received and pending review',
            'payment_notify_review_message' => "We received your payment request for :plan and it is now pending review.\n\nGateway: :gateway.\nStatus: :status.\nMessage: :message.",
            'payment_notify_cancel_status' => '1',
            'payment_notify_cancel_subject' => 'Payment or subscription cancelled',
            'payment_notify_cancel_message' => "Your payment/subscription action for :plan was cancelled.\n\nGateway: :gateway.\nStatus: :status.\nMessage: :message.",
        ];
    }

    public function buildContext(
        User $user,
        ?AdminPlan $plan = null,
        ?PaymentCallbackResult $result = null,
        ?PaymentHistory $paymentHistory = null,
        ?PaymentSubscription $subscription = null,
        array $extra = [],
    ): array {
        $amount = $paymentHistory?->amount
            ?? $subscription?->amount
            ?? $result?->amount;
        $currency = $paymentHistory?->currency
            ?? $subscription?->currency
            ?? $result?->currency;

        return array_merge([
            'name' => (string) ($user->name ?: $user->username ?: 'User'),
            'username' => (string) ($user->username ?: ''),
            'email' => (string) ($user->email ?: ''),
            'plan' => (string) ($plan?->name ?: $paymentHistory?->plan?->name ?: $subscription?->plan?->name ?: __('your plan')),
            'amount' => $amount !== null ? number_format((float) $amount, 2) : '0.00',
            'currency' => (string) ($currency ?: 'USD'),
            'gateway' => (string) ($paymentHistory?->from ?: $subscription?->service ?: ($extra['gateway'] ?? 'payment gateway')),
            'transaction_id' => (string) ($paymentHistory?->transaction_id ?: $result?->transactionId ?: ($extra['transaction_id'] ?? 'N/A')),
            'subscription_id' => (string) ($subscription?->subscription_id ?: $result?->subscriptionId ?: ($extra['subscription_id'] ?? '')),
            'status' => (string) ($result?->status ?: ($extra['status'] ?? '')),
            'message' => (string) ($result?->message ?: ($extra['message'] ?? __('No additional details were provided.'))),
            'app_name' => (string) config('app.name', 'Stackposts'),
        ], $extra);
    }

    protected function enabled(string $event): bool
    {
        return $this->options->get($this->key($event, 'status'), '0') === '1';
    }

    protected function subject(string $event): string
    {
        return (string) $this->options->get($this->key($event, 'subject'), self::defaults()[$this->key($event, 'subject')] ?? '');
    }

    protected function message(string $event): string
    {
        return (string) $this->options->get($this->key($event, 'message'), self::defaults()[$this->key($event, 'message')] ?? '');
    }

    protected function key(string $event, string $field): string
    {
        return 'payment_notify_'.$event.'_'.$field;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function render(string $content, User $user, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $replacements[':'.$key] = (string) ($value ?? '');
        }

        $replacements[':name'] = $replacements[':name'] ?? (string) ($user->name ?: $user->username ?: 'User');
        $replacements[':email'] = $replacements[':email'] ?? (string) $user->email;

        return strtr($content, $replacements);
    }
}
