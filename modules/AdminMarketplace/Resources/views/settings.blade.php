<section class="w-full">
    <x-settings.layout :heading="__('Marketplace Settings')" :subheading="__('Control whether marketplace purchase packages can upgrade themselves automatically through the scheduler.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Automatic upgrades')"
                :description="__('Only marketplace purchase packages are eligible. ZIP installs remain manual because they do not carry remote license update metadata.')"
                body-class="space-y-6 p-6"
            >
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.radio-group
                        name="marketplace_auto_update_status"
                        wire:model.live="marketplace_auto_update_status"
                        :label="__('Auto-update status')"
                        :options="$toggleOptions"
                        :value="$marketplace_auto_update_status"
                    />

                    <div class="rounded-[1.05rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Scheduler requirement') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-header-text-color);">{{ __('Auto-update runs through the Laravel scheduler. Keep the main scheduler cron active on the server, otherwise nothing will execute.') }}</p>
                        <pre class="mt-4 overflow-x-auto rounded-[1rem] px-4 py-4 text-sm" style="background-color: #1f2430; color: #5af78e;"><code>{{ $schedulerCommand }}</code></pre>
                    </div>
                </div>

                <x-ui.alert
                    variant="neutral"
                    :title="__('How it works')"
                    :description="__('During each scheduled run, the app checks installed marketplace purchase packages against the latest catalog versions. When a newer version is available, it downloads and installs the update automatically using the saved purchase code and product id.')"
                />
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
