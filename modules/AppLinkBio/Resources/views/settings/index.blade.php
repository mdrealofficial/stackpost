<section class="w-full">
    <x-settings.layout
        :heading="__('Link Bio Settings')"
        :subheading="__('Set the defaults used when users create new bio pages or generate AI bio drafts.')"
    >
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Creation defaults')"
                :description="__('These values apply only to newly created Link Bio pages. Existing pages keep their current settings.')"
                body-class="space-y-6 p-6"
            >
                <x-ui.field :label="__('Default template')" :error="$errors->first('defaultTemplate')">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($templates as $template)
                            <button
                                type="button"
                                wire:click="setDefaultTemplate('{{ $template['key'] }}')"
                                class="group overflow-hidden rounded-2xl border bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4"
                                style="{{ $defaultTemplate === $template['key'] ? 'border-color: var(--theme-accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-accent) 18%, transparent);' : 'border-color: var(--theme-border-color);' }}"
                            >
                                <div class="h-24 p-3" style="background: {{ data_get($template, 'theme.page_bg', '#f8fafc') }};">
                                    <div class="mx-auto h-full w-24 overflow-hidden rounded-xl border" style="border-color: {{ data_get($template, 'theme.surface_border', 'rgba(15,23,42,0.14)') }}; background: {{ data_get($template, 'theme.shell_bg', '#ffffff') }};">
                                        <div class="h-9" style="background: {{ $template['preview'] }}"></div>
                                        <div class="p-2">
                                            <div class="h-2 w-10 rounded-full" style="background: {{ data_get($template, 'theme.text', '#0f172a') }};"></div>
                                            <div class="mt-1.5 h-1.5 w-14 rounded-full" style="background: {{ data_get($template, 'theme.muted', '#64748b') }}; opacity:.7;"></div>
                                            <div class="mt-2 h-4 rounded-full" style="background: {{ data_get($template, 'theme.button_bg', data_get($template, 'theme.accent', '#2563eb')) }};"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-start justify-between gap-3 p-4">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $template['label'] }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ $template['category'] }}</p>
                                    </div>
                                    @if ($defaultTemplate === $template['key'])
                                        <span class="grid h-7 w-7 place-items-center rounded-full text-white" style="background: var(--theme-accent);">
                                            <i class="fa-light fa-check text-xs"></i>
                                        </span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </x-ui.field>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.field :label="__('Default page status')" :error="$errors->first('defaultStatus')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.radio name="link-bio-default-status" value="draft" wire:model.defer="defaultStatus" :label="__('Draft')" />
                            <x-ui.radio name="link-bio-default-status" value="published" wire:model.defer="defaultStatus" :label="__('Published')" />
                        </div>
                    </x-ui.field>

                    <x-ui.input
                        wire:model.defer="defaultBrandingText"
                        :label="__('Default footer branding text')"
                        :error="$errors->first('defaultBrandingText')"
                        :help="__('Used when a plan does not allow custom branding, and as the starting value for new pages.')"
                    />
                </div>
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">
                    <i class="fa-light fa-floppy-disk"></i>
                    {{ __('Save changes') }}
                </x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
