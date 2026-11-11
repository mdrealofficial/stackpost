<?php

namespace Modules\AppBilling\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AppBilling\Support\InvoicePdfBuilder;
use Modules\AppPayments\Support\PaymentManager;
use Modules\AppPayments\Support\PaymentNotificationService;

class AppBillingController extends Controller
{
    public function __construct(
        protected PaymentManager $payments,
        protected PaymentNotificationService $notifications,
    ) {}

    public function billing(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing(['plan', 'nextPlan']);

        $subscriptions = PaymentSubscription::query()
            ->with('plan')
            ->where('uid', $user?->id)
            ->orderByDesc('created')
            ->limit(5)
            ->get();

        $payments = PaymentHistory::query()
            ->with('plan')
            ->where('uid', $user?->id)
            ->orderByDesc('created')
            ->limit(5)
            ->get();

        $latestSubscription = $subscriptions->first();
        $activeRecurringSubscription = PaymentSubscription::activeRecurringForUser((int) $user?->id);
        $allPayments = PaymentHistory::query()
            ->where('uid', $user?->id)
            ->get(['status', 'amount']);

        return view('appbilling::billing', [
            'user' => $user,
            'plan' => $user?->plan,
            'creditSummary' => credit_summary($user),
            'latestSubscription' => $latestSubscription,
            'activeRecurringSubscription' => $activeRecurringSubscription,
            'subscriptions' => $subscriptions,
            'recentPayments' => $payments,
            'summary' => [
                'totalInvoices' => $allPayments->count(),
                'paidInvoices' => $allPayments->where('status', 1)->count(),
                'pendingInvoices' => $allPayments->whereNotIn('status', [0, 1])->count(),
                'lifetimeValue' => (float) $allPayments->where('status', 1)->sum('amount'),
            ],
        ]);
    }

    public function invoices(Request $request): View
    {
        $user = $request->user();

        $invoices = PaymentHistory::query()
            ->with('plan')
            ->where('uid', $user?->id)
            ->orderByDesc('created')
            ->paginate(12)
            ->withQueryString();

        return view('appbilling::invoices', [
            'invoices' => $invoices,
        ]);
    }

    public function cancelSubscription(Request $request, PaymentSubscription $subscription): RedirectResponse
    {
        abort_unless((int) $subscription->uid === (int) $request->user()?->id, 404);

        if (! $subscription->canBeCancelledByUser()) {
            return redirect()
                ->route('portal.billing')
                ->with('error', __('This subscription cannot be cancelled from the portal.'));
        }

        try {
            $arguments = [
                'subscriptionId' => (string) $subscription->subscription_id,
                'reason' => 'Cancelled by the customer from the billing portal.',
                'atPeriodEnd' => false,
            ];

            $cancelled = (bool) $this->payments->call((string) $subscription->service, 'cancel_recurring', $arguments);
        } catch (\Throwable $e) {
            return redirect()
                ->route('portal.billing')
                ->with('error', $e->getMessage());
        }

        if (! $cancelled) {
            return redirect()
                ->route('portal.billing')
                ->with('error', __('The recurring subscription could not be cancelled at the payment gateway.'));
        }

        $subscription->forceFill([
            'status' => 2,
            'changed' => time(),
        ])->save();

        if ($request->user()) {
            $this->notifications->notify(
                $request->user(),
                'cancel',
                $this->notifications->buildContext(
                    user: $request->user(),
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

        return redirect()
            ->route('portal.billing')
            ->with('status', __('Recurring subscription cancelled successfully.'));
    }

    public function downloadInvoice(Request $request, PaymentHistory $invoice, InvoicePdfBuilder $pdfBuilder): Response
    {
        abort_unless((int) $invoice->uid === (int) $request->user()?->id, 404);

        $user = $request->user();
        $appName = (string) config('app.name', 'Stackposts');
        $createdAt = $invoice->createdAtFormatted('Y-m-d H:i') ?: 'N/A';
        $amount = ($invoice->currency ?: 'USD').' '.number_format((float) $invoice->amount, 2);

        $pdf = $pdfBuilder->make([
            'app_name' => $appName,
            'invoice_ref' => $invoice->id_secure ?: 'N/A',
            'transaction_id' => $invoice->transaction_id ?: 'N/A',
            'customer_name' => $user?->name ?: 'N/A',
            'customer_username' => $user?->username ?: 'user',
            'customer_email' => $user?->email ?: 'N/A',
            'plan' => $invoice->plan?->name ?: 'N/A',
            'gateway' => $invoice->from ?: 'N/A',
            'source' => $invoice->by ?: 'N/A',
            'amount' => $amount,
            'status' => $invoice->statusLabel(),
            'issued_at' => $createdAt,
            'generated_at' => now()->format('Y-m-d H:i'),
            'line_description' => ($invoice->plan?->name ?: 'Subscription charge').' via '.($invoice->from ?: 'billing gateway'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.($invoice->id_secure ?: $invoice->id).'.pdf"',
        ]);
    }
}
