<section class="w-full">
    <x-settings.layout :heading="__('Admin Faker')" :subheading="__('Create a safe preview workspace with demo channels, daily schedules, RSS, blogs, FAQs, AI publishing, and AI logs before a customer buys.')">
        <div class="space-y-6">
            @if ($statusMessage)
                <x-ui.alert :variant="$statusVariant" :description="$statusMessage" />
            @endif

            <x-theme.section-card
                :title="__('Demo Account')"
                :description="__('Admin Faker now uses the first user in the system as the demo account, typically the main admin account.')"
                body-class="p-6"
            >
                @if ($previewUser)
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('User') }}</p>
                            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $previewUser->name }}</p>
                        </div>

                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Email') }}</p>
                            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $previewUser->email }}</p>
                        </div>

                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('User ID') }}</p>
                            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">#{{ $previewUser->id }}</p>
                        </div>
                    </div>
                @else
                    <x-ui.alert
                        inline
                        variant="danger"
                        :title="__('No demo user found')"
                        :description="__('Create at least one user first. Admin Faker will use the first user record automatically.')"
                    />
                @endif

                <div class="mt-5">
                    <x-ui.checkbox
                        wire:model.defer="clearBeforeSeed"
                        name="clear_before_seed"
                        value="1"
                        :checked="$clearBeforeSeed"
                        :label="__('Clear old demo schedules before generating')"
                        :description="__('Only records tagged by Admin Faker are removed, then fresh demo data is created.')"
                    />
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($previewUser)
                        <x-ui.button type="button" wire:click="seedDemoData" wire:loading.attr="disabled" wire:target="seedDemoData,clearDemoData">
                            <i class="fa-light fa-wand-magic-sparkles"></i>
                            {{ __('Generate Demo Workspace') }}
                        </x-ui.button>

                        <x-ui.button type="button" variant="outline" wire:click="clearDemoData" wire:loading.attr="disabled" wire:target="seedDemoData,clearDemoData">
                            <i class="fa-light fa-trash-can-clock"></i>
                            {{ __('Clear Demo Workspace') }}
                        </x-ui.button>
                    @else
                        <x-ui.button type="button" disabled>
                            <i class="fa-light fa-wand-magic-sparkles"></i>
                            {{ __('Generate Demo Workspace') }}
                        </x-ui.button>

                        <x-ui.button type="button" variant="outline" disabled>
                            <i class="fa-light fa-trash-can-clock"></i>
                            {{ __('Clear Demo Workspace') }}
                        </x-ui.button>
                    @endif
                </div>
            </x-theme.section-card>

            @if ($result !== [])
                <x-theme.section-card
                    :title="__('Latest Result')"
                    :description="__('The first user account was used as the demo account and only demo-tagged records were managed.')"
                    body-class="p-6"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Login email') }}</p>
                            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $result['user']['email'] ?? '' }}</p>
                        </div>

                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Password') }}</p>
                            <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $result['user']['password_hint'] ?? __('Preserved existing password') }}</p>
                        </div>
                    </div>

                    @if (! empty($result['counts']))
                        <div class="mt-6 grid gap-4 md:grid-cols-3">
                            @foreach ($result['counts'] as $label => $count)
                                <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                                    <p class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ str($label)->replace('_', ' ')->headline() }}</p>
                                    <p class="mt-2 text-2xl font-semibold" style="color: var(--theme-header-text-color);">{{ $count }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-theme.section-card>
            @endif
        </div>
    </x-settings.layout>
</section>
