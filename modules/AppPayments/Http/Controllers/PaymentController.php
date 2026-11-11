<?php

namespace Modules\AppPayments\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AppPayments\Support\PaymentCheckoutStore;
use Modules\AppPayments\Support\PaymentCheckoutTypeRegistry;
use Modules\AppPayments\Support\PaymentLifecycleService;
use Modules\AppPayments\Support\PaymentManager;
use Modules\AppPayments\Support\PaymentNotificationService;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentManager $payments,
        protected PaymentCheckoutStore $checkoutStore,
        protected PaymentCheckoutTypeRegistry $types,
        protected PaymentLifecycleService $lifecycle,
        protected PaymentNotificationService $notifications,
    ) {}

    public function success(Request $request, string $gateway): View|RedirectResponse
    {
        $checkout = $this->checkoutStore->current($gateway);

        if (! $checkout) {
            return redirect()->route('guest.pricing')->with('error', __('Payment session not found or already expired.'));
        }

        try {
            $result = $this->payments->gateway($gateway)->complete($request, $checkout);
        } catch (\Throwable $e) {
            $this->checkoutStore->forget();

            $retryRoute = $this->types->resolve($checkout)->retryUrl($checkout);

            return redirect()
                ->to($retryRoute)
                ->with('error', $e->getMessage());
        }
        $paymentHistory = null;

        if ($result->isSuccessful()) {
            $paymentHistory = $this->lifecycle->finalize($checkout, $result);
            $this->checkoutStore->forget();
            $user = $request->user();

            if ($user) {
                $this->notifications->notify(
                    $user,
                    'success',
                    $this->notifications->buildContext(
                        user: $user,
                        plan: $paymentHistory->plan,
                        result: $result,
                        paymentHistory: $paymentHistory,
                    )
                );
            }
        } elseif ($request->user()) {
            $this->notifications->notify(
                $request->user(),
                $result->status === 'pending' ? 'review' : 'failed',
                $this->notifications->buildContext(
                    user: $request->user(),
                    plan: null,
                    result: $result,
                    paymentHistory: null,
                    subscription: null,
                    extra: [
                        'gateway' => $checkout->gateway,
                    ],
                )
            );
        }

        return view('apppayments::success', [
            'status' => $result->status,
            'message' => $result->message,
            'paymentHistory' => $paymentHistory,
            'checkout' => $checkout,
            'transactionId' => $result->transactionId,
            'meta' => $result->meta,
        ]);
    }

    public function cancel(Request $request, string $gateway): View
    {
        if ($request->user()) {
            $this->notifications->notify(
                $request->user(),
                'cancel',
                $this->notifications->buildContext(
                    user: $request->user(),
                    extra: [
                        'gateway' => $gateway,
                        'status' => 'cancelled',
                        'message' => __('The checkout was cancelled before the payment provider confirmed the charge.'),
                    ],
                )
            );
        }

        return view('apppayments::cancel', [
            'gateway' => $gateway,
        ]);
    }

    public function webhook(Request $request, string $gateway): JsonResponse|Response
    {
        $payload = $this->payments->gateway($gateway)->webhook($request);

        if ($payload instanceof Response || $payload instanceof JsonResponse) {
            return $payload;
        }

        if (is_string($payload)) {
            return response($payload, 200)->header('Content-Type', 'text/plain');
        }

        if (is_array($payload)) {
            return response()->json([
                'status' => 'ok',
                'gateway' => $gateway,
                'payload' => $payload,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'gateway' => $gateway,
            'payload' => $payload,
        ]);
    }
}
