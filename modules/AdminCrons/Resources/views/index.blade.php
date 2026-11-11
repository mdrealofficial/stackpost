<section class="w-full">
    <x-settings.layout :heading="__('System Crons')" :subheading="__('Manage every cron entry point in one place, including the secure URL key and the command lines your server should run.')">
        <div class="space-y-6">
            @if ($statusMessage)
                <x-ui.alert :variant="$statusVariant" :description="$statusMessage" />
            @endif

            <x-theme.section-card
                :title="__('Secure Cron Key')"
                :description="__('Use this secret key to protect URL-based cron execution. Rotate it if the key is exposed or after server access changes.')"
                body-class="p-6"
            >
                <div class="space-y-4">
                    <div class="flex overflow-hidden rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div class="inline-flex h-12 w-14 items-center justify-center border-r" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-accent);">
                            <i class="fa-light fa-key"></i>
                        </div>
                        <div class="flex min-w-0 flex-1 items-center px-4 text-sm font-semibold tracking-[0.08em]" style="color: var(--theme-header-text-color);">
                            {{ $secureKey }}
                        </div>
                        <x-ui.button type="button" wire:click="regenerateKey" variant="secondary" class="rounded-none border-0">
                            <i class="fa-light fa-rotate"></i>
                            {{ __('Change Key') }}
                        </x-ui.button>
                    </div>

                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Any existing URL cron that uses the old key will stop working immediately after rotation.') }}</p>
                </div>
            </x-theme.section-card>

            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('List Cron') }}</h2>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Recommended setup: run the Laravel scheduler every minute. The direct command entries below are useful for debugging or isolated environments.') }}</p>
                </div>

                @foreach ($tasks as $task)
                    <x-theme.section-card
                        :title="$task['name']"
                        :description="$task['description']"
                        body-class="p-0"
                    >
                        <div class="border-t" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <div class="space-y-5 p-6">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                                        <i class="{{ $task['icon'] }}"></i>
                                    </span>
                                    @if ($task['recommended'] ?? false)
                                        <x-ui.badge variant="primary">{{ __('Recommended') }}</x-ui.badge>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Use cron with URL') }}</p>
                                    <pre class="overflow-x-auto rounded-[1rem] px-4 py-4 text-sm" style="background-color: #1f2430; color: #5af78e;"><code>{{ $task['linux_cron'] }}</code></pre>
                                </div>

                                <div class="space-y-2">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Use cron with Artisan Command') }}</p>
                                    <pre class="overflow-x-auto rounded-[1rem] px-4 py-4 text-sm" style="background-color: #1f2430; color: #5af78e;"><code>{{ $task['artisan_cron'] }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </x-theme.section-card>
                @endforeach
            </div>
        </div>
    </x-settings.layout>
</section>
