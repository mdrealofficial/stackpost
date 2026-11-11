@component(theme_view('layouts.app', 'app'), ['title' => __('Affiliate')])
    @php
        $metricCards = [
            ['label' => __('Clicks'), 'value' => number_format($profile->clicks), 'description' => __('Referral link visits captured from your code.'), 'icon' => 'fa-light fa-arrow-pointer', 'tone' => 'rgb(37, 99, 235)', 'iconSurface' => 'rgba(59, 130, 246, 0.10)'],
            ['label' => __('Conversions'), 'value' => number_format($profile->conversions), 'description' => __('Successful commission events attributed to you.'), 'icon' => 'fa-light fa-badge-check', 'tone' => 'rgb(5, 150, 105)', 'iconSurface' => 'rgba(16, 185, 129, 0.10)'],
            ['label' => __('Available balance'), 'value' => number_format((float) $profile->total_balance, 2), 'suffix' => $walletCurrency ?: 'USD', 'description' => __('Current balance available for withdrawal.'), 'icon' => 'fa-light fa-wallet', 'tone' => 'rgb(217, 119, 6)', 'iconSurface' => 'rgba(245, 158, 11, 0.12)'],
            ['label' => __('Approved total'), 'value' => number_format((float) $profile->total_approved, 2), 'suffix' => $walletCurrency ?: 'USD', 'description' => __('Lifetime approved affiliate earnings.'), 'icon' => 'fa-light fa-sack-dollar', 'tone' => 'rgb(79, 70, 229)', 'iconSurface' => 'rgba(99, 102, 241, 0.10)'],
        ];
    @endphp

    <div class="portal-tools space-y-5">
        

        @if ($errors->has('affiliate_withdrawal'))
            <x-ui.alert :title="__('Unable to submit request')" :description="$errors->first('affiliate_withdrawal')" variant="danger" dismissible />
        @endif

        @include('partials.portal-tools-style')<x-ui.sub-header compact :title="__('Affiliate')" :description="__('Share your referral link and track commissions and payout requests.')"><x-slot:actions></x-slot:actions></x-ui.sub-header><p class="portal-tool-notice">{{ __('Wallet currency') }}: <strong>{{ $walletCurrency ?: __('Needs review') }}</strong>. {{ __('Payout requests are reviewed manually.') }}</p>

        <x-ui.metric-strip
            :items="$metricCards"
            columns="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4"
            gap="gap-5"
            variant="portal"
            :show-progress="false"
            aria-label="{{ __('Affiliate performance summary') }}"
        />

        <div class="grid gap-5 xl:grid-cols-2 xl:items-stretch">
            <x-ui.card class="flex h-full flex-col space-y-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                        <i class="fa-light fa-link-simple"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Referral assets') }}</h3>
                        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use this code or direct link anywhere you promote your referral offer.') }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <x-ui.input :label="__('Referral code')" :value="$referralCode" readonly />
                    <x-ui.input :label="__('Referral link')" :value="$referralLink" readonly /><x-ui.button type="button" variant="outline" :data-copy="$referralLink" x-data="{}" x-on:click="navigator.clipboard.writeText($el.dataset.copy).then(() => window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'success',message:'Referral link copied.'}}))).catch(() => window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'error',message:'Copy failed. Select the link to copy it.'}})))">{{ __('Copy link') }}</x-ui.button>
                </div>

                <div class="mt-auto rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">
                        <i class="fa-light fa-bullhorn" style="color: var(--theme-accent-color);"></i>
                        {{ __('Sharing note') }}
                    </p>
                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Use the full link in social posts, bios, direct messages, and email campaigns so every referral visit is tracked correctly.') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card class="flex h-full flex-col space-y-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(245, 158, 11, 0.12); color: rgb(217, 119, 6);">
                        <i class="fa-light fa-money-bill-transfer"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Request withdrawal') }}</h3>
                        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Minimum withdrawal: :amount', ['amount' => number_format($minimumWithdrawal, 2)]) }}</p>
                    </div>
                </div>

                @if((float)$profile->total_balance < $minimumWithdrawal)<p class="portal-tool-notice">{{ __('You need :amount more before requesting a payout.', ['amount'=>number_format(max(0,$minimumWithdrawal-(float)$profile->total_balance),2).' '.$walletCurrency]) }}</p>@endif
                @if($walletCurrency && (float)$profile->total_balance >= $minimumWithdrawal)<form method="POST" action="{{ route('portal.affiliate.withdraw') }}" class="flex h-full flex-col space-y-4">
                    @csrf
                    <x-ui.input :max="$profile->total_balance" name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('amount')" :error="$errors->first('amount')" required />
                    <x-ui.input name="payment_method" :label="__('Payment method')" :value="old('payment_method')" :error="$errors->first('payment_method')" :placeholder="__('Bank transfer, PayPal, USDT...')" required />
                    <x-ui.textarea name="payment_details" :label="__('Payment details')" :error="$errors->first('payment_details')" rows="5" :placeholder="__('Account number, wallet address, account holder, and payout notes.')">{{ old('payment_details') }}</x-ui.textarea>
                    <div class="mt-auto pt-1">
                        <x-ui.button type="submit" :disabled="!$walletCurrency || (float)$profile->total_balance < $minimumWithdrawal">
                            <i class="fa-light fa-paper-plane-top"></i>
                            {{ __('Request payout') }}
                        </x-ui.button>
                    </div>
                </form>@else<p class="text-sm">{{ __('Payout requests become available when your eligible balance reaches the minimum.') }}</p>@endif
            </x-ui.card>
        </div>

        <div class="grid gap-5 xl:grid-cols-2 xl:items-stretch">
            <x-ui.datatable-shell class="h-full" :title="__('Recent commissions')" :info="__('Your latest affiliate commission records.')">
                <x-slot:toolbar>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl" style="background: rgba(16, 185, 129, 0.10); color: rgb(5, 150, 105);">
                        <i class="fa-light fa-badge-dollar"></i>
                    </span>
                </x-slot:toolbar>
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Payment') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Commission') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @forelse ($commissions as $commission)
                            <x-ui.table-row>
                                <x-ui.table-cell><div class="flex items-start gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);"><i class="fa-light fa-user-check"></i></span><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $commission->referredUser?->name ?: __('Unknown user') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $commission->referredUser?->email ?: __('No email') }}</p></div></div></x-ui.table-cell>
                                <x-ui.table-cell><div class="space-y-1"><p class="text-sm" style="color: var(--theme-header-text-color);">{{ $commission->paymentHistory?->transaction_id ?: __('N/A') }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ strtoupper((string) ($commission->paymentHistory?->currency ?: 'USD')) }} / {{ $commission->paymentHistory?->from ?: __('Unknown') }}</p></div></x-ui.table-cell>
                                <x-ui.table-cell><div class="space-y-1"><p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((float) $commission->commission, 2) }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ number_format((float) $commission->commission_rate, 2) }}%</p></div></x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :variant="$commission->statusVariant()">{{ $commission->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="4" class="py-10">
                                    <x-ui.empty icon="fa-light fa-badge-dollar" :title="__('No commissions yet.')" :description="__('Once referred users complete eligible payments, the records will show here.')" />
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            <div class="tool-pagination">{{ $commissions->links() }}</div></x-ui.datatable-shell>

            <x-ui.datatable-shell class="h-full" :title="__('Withdrawal history')" :info="__('Your latest withdrawal requests and payout status.')">
                <x-slot:toolbar>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl" style="background: rgba(245, 158, 11, 0.12); color: rgb(217, 119, 6);">
                        <i class="fa-light fa-money-bill-transfer"></i>
                    </span>
                </x-slot:toolbar>
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Request') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Method') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Amount') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @forelse ($withdrawals as $withdrawal)
                            <x-ui.table-row>
                                <x-ui.table-cell><div class="flex items-start gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background: rgba(245, 158, 11, 0.12); color: rgb(217, 119, 6);"><i class="fa-light fa-receipt"></i></span><div class="space-y-1"><p class="font-medium" style="color: var(--theme-header-text-color);">{{ $withdrawal->id_secure }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $withdrawal->createdAtFormatted() ?: __('N/A') }}</p></div></div></x-ui.table-cell>
                                <x-ui.table-cell><div class="space-y-1"><p class="text-sm" style="color: var(--theme-header-text-color);">{{ $withdrawal->payment_method }}</p><p class="line-clamp-2 text-xs" style="color: var(--theme-muted-text-color);">{{ $withdrawal->payment_details }}</p></div></x-ui.table-cell>
                                <x-ui.table-cell><span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format((float) $withdrawal->amount, 2) }}</span></x-ui.table-cell>
                                <x-ui.table-cell><x-ui.badge :variant="$withdrawal->statusVariant()">{{ $withdrawal->statusLabel() }}</x-ui.badge></x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="4" class="py-10">
                                    <x-ui.empty icon="fa-light fa-money-bill-transfer" :title="__('No withdrawals yet.')" :description="__('Your submitted payout requests will appear here.')" />
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            <div class="tool-pagination">{{ $withdrawals->links() }}</div></x-ui.datatable-shell>
        </div>
    </div>
@endcomponent
