<?php

namespace Modules\AppAffiliate\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AppAffiliate\Models\AffiliateCommission;
use Modules\AppAffiliate\Models\AffiliateWithdrawal;
use Modules\AppAffiliate\Support\AffiliateService;
use RuntimeException;

class PortalAffiliateController extends Controller
{
    public function __construct(
        protected AffiliateService $affiliate,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->canAccess($request), 404);

        $user = $request->user();
        $profile = $this->affiliate->ensureProfile($user);

        return view('appaffiliate::index', [
            'profile' => $profile,
            'walletCurrency' => $this->affiliate->walletCurrency($user),
            'referralCode' => $this->affiliate->ensureReferralCode($user),
            'referralLink' => $this->affiliate->referralLink($user),
            'minimumWithdrawal' => $this->affiliate->minimumWithdrawal(),
            'commissions' => AffiliateCommission::query()
                ->with(['referredUser:id,name,email,username', 'paymentHistory:id,transaction_id,from,currency'])
                ->where('affiliate_user_id', $user->id)
                ->latest('id')
                ->paginate(10, ['*'], 'commissions_page')->withQueryString(),
            'withdrawals' => AffiliateWithdrawal::query()
                ->where('affiliate_user_id', $user->id)
                ->latest('id')
                ->paginate(10, ['*'], 'withdrawals_page')->withQueryString(),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        abort_unless($this->canAccess($request), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:120'],
            'payment_details' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $withdrawal = $this->affiliate->requestWithdrawal(
                $request->user(),
                (float) $validated['amount'],
                (string) $validated['payment_method'],
                (string) ($validated['payment_details'] ?? ''),
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('portal.affiliate.index')
                ->withErrors(['affiliate_withdrawal' => $exception->getMessage()])
                ->withInput();
        }

        log_activity('portal.affiliate.withdraw', 'Submitted an affiliate withdrawal request.', [
            'subject_type' => AffiliateWithdrawal::class,
            'subject_id' => $withdrawal->id,
            'metadata' => [
                'withdrawal' => $withdrawal->id_secure,
                'amount' => $withdrawal->amount,
            ],
        ]);

        return redirect()
            ->route('portal.affiliate.index')
            ->with('status', __('Withdrawal request submitted successfully.'));
    }

    protected function canAccess(Request $request): bool
    {
        return $this->affiliate->userCanAccess($request->user());
    }
}
