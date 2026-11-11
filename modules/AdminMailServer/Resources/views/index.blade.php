<section class="w-full">
    <x-settings.layout :heading="__('Mail Server')" :subheading="__('Configure delivery transport, sender identity, and test the mail pipeline before enabling SaaS notifications and transactional emails.')">
        <form wire:submit="save" class="my-6 w-full space-y-6" x-data="{ transport: @entangle('mail_protocol').live }">
            <x-theme.section-card
                :title="__('Delivery overview')"
                :description="__('Choose the mail transport first, then complete only the fields that transport actually needs.')"
                body-class="space-y-6 p-6"
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-[1.05rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Transport') }}</p>
                        <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $effectiveTransport }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Effective default mailer used by the application.') }}</p>
                    </div>

                    <div class="rounded-[1.05rem] border px-4 py-4 md:col-span-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Sender identity') }}</p>
                        <p class="mt-2 text-lg font-semibold break-all" style="color: var(--theme-header-text-color);">{{ $effectiveSender }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('This name and address are applied globally for outbound mail.') }}</p>
                    </div>
                </div>

                <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                    <div class="space-y-2.5">
                        <span class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Mail transport') }}</span>

                        <div class="flex flex-wrap gap-x-6 gap-y-3">
                            @foreach ($transportOptions as $option)
                                <x-ui.radio
                                    name="mail_protocol"
                                    :value="$option['value']"
                                    :checked="(string) $mail_protocol === (string) $option['value']"
                                    :label="__($option['label'])"
                                    :description="__($option['description'])"
                                    wire:model.live="mail_protocol"
                                />
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Sender')" 
                :description="__('Use a sender identity that matches your verified domain to reduce spoofing and deliverability issues.')"
                body-class="grid gap-5 p-6 md:grid-cols-2"
            >
                <x-ui.input wire:model="mail_sender_name" type="text" :label="__('Sender Name')" :error="$errors->first('mail_sender_name')" placeholder="{{ __('Stackposts Notifications') }}" />
                <x-ui.input wire:model="mail_sender_email" type="email" :label="__('Sender Email')" :error="$errors->first('mail_sender_email')" placeholder="no-reply@example.com" />
            </x-theme.section-card>

            <div x-show="transport === 'smtp'" x-cloak>
                <x-theme.section-card
                    :title="__('SMTP credentials')"
                    :description="__('Use authenticated SMTP from your transactional mail provider. These fields are ignored when another transport is selected.')"
                    body-class="space-y-5 p-6"
                >
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.input wire:model="smtp_server" type="text" :label="__('SMTP Host')" :error="$errors->first('smtp_server')" placeholder="smtp.mailgun.org" />
                        <x-ui.input wire:model="smtp_port" type="number" :label="__('SMTP Port')" :error="$errors->first('smtp_port')" placeholder="587" />
                        <x-ui.input wire:model="smtp_username" type="text" :label="__('SMTP Username')" :error="$errors->first('smtp_username')" placeholder="{{ __('Usually your provider username or API key id') }}" />
                        <x-ui.input wire:model="smtp_password" type="password" :label="__('SMTP Password')" :error="$errors->first('smtp_password')" placeholder="••••••••••••" />
                        <x-ui.select wire:model.live="smtp_encryption" :label="__('Encryption')" :error="$errors->first('smtp_encryption')">
                            @foreach ($encryptionOptions as $option)
                                <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input wire:model="mail_timeout" type="number" :label="__('Timeout (seconds)')" :error="$errors->first('mail_timeout')" placeholder="30" />
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.input wire:model="mail_ehlo_domain" type="text" :label="__('EHLO Domain')" :error="$errors->first('mail_ehlo_domain')" :help="__('Optional. Useful when your SMTP provider expects a specific domain in the handshake.')" placeholder="mail.example.com" />
                    </div>
                </x-theme.section-card>
            </div>

            <div x-show="transport === 'sendmail'" x-cloak>
                <x-theme.section-card
                    :title="__('Sendmail path')"
                    :description="__('Use this only when the server itself is configured to relay mail. This is usually less portable than SMTP for SaaS deployments.')"
                    body-class="space-y-5 p-6"
                >
                    <x-ui.input wire:model="sendmail_path" type="text" :label="__('Sendmail Command')" :error="$errors->first('sendmail_path')" placeholder="/usr/sbin/sendmail -bs -i" />
                </x-theme.section-card>
            </div>

            <x-theme.section-card
                :title="__('Test delivery')"
                :description="__('Send a one-off test using the current form state. You can verify a new SMTP profile before saving it permanently.')"
                body-class="space-y-5 p-6"
            >
                @if ($testStatus && $testMessage)
                    <x-ui.alert :variant="$testStatus" :title="$testStatus === 'success' ? __('Test completed') : __('Test failed')" :description="$testMessage" />
                @endif

                <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                    <x-ui.input wire:model="test_email" type="email" :label="__('Recipient Email')" :error="$errors->first('test_email')" placeholder="owner@example.com" />
                    <x-ui.button type="button" variant="outline" wire:click="sendTestEmail">{{ __('Send test email') }}</x-ui.button>
                </div>

                <x-ui.alert variant="neutral" :title="__('Recommended rollout')" :description="__('Start with the log mailer in local environments, validate SMTP with a real inbox, then switch transactional notifications and queued jobs over once delivery is confirmed.')" />
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
