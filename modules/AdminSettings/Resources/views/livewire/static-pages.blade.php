<section class="w-full">
    <x-settings.layout :heading="__('Static pages')" :subheading="__('Manage public legal and information pages directly from admin settings.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            @foreach ($pages as $page)
                @php($key = $page['key'])

                <x-theme.section-card
                    :title="$page['default_title']"
                    :description="$page['description']"
                    body-class="space-y-5 p-6"
                >
                    <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_18rem]">
                        <x-ui.input
                            wire:model="{{ $key }}_title"
                            :label="__('Page title')"
                            type="text"
                            :error="$errors->first($key.'_title')"
                        />

                        <x-ui.input
                            :value="route($page['route_name'])"
                            :label="__('Public URL')"
                            type="text"
                            readonly
                        />
                    </div>

                    @if (($page['type'] ?? 'html') === 'social')
                        <x-ui.textarea
                            wire:model.live="social_pages_intro"
                            :label="__('Intro text')"
                            rows="4"
                            :error="$errors->first('social_pages_intro')"
                            :help="__('Optional short introduction shown above the social links list.')"
                        />

                        <div class="grid gap-5 md:grid-cols-2">
                            @foreach ($socialNetworks as $network)
                                <x-ui.input
                                    wire:model.live="{{ $network['property'] }}"
                                    :label="$network['label'].' URL'"
                                    type="url"
                                    :placeholder="'https://'"
                                    :error="$errors->first($network['property'])"
                                />
                            @endforeach
                        </div>
                    @else
                        <div
                            x-data="{ content: @entangle($key.'_content').live }"
                            x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('html-editor:set', { detail: { name: '{{ $key }}_content_editor', content: content || '' } })))"
                            x-on:html-editor:change="
                                if (($event.detail?.name || '') === '{{ $key }}_content_editor') {
                                    content = $event.detail?.content || '';
                                }
                            "
                        >
                            <x-ui.html-editor
                                :name="$key.'_content_editor'"
                                :label="__('Content')"
                                :value="$this->{$key.'_content'}"
                                :error="$errors->first($key.'_content')"
                                :help="__('Content uses the HTML editor and will be rendered on the public page.')"
                                rows="14"
                            />

                            <input type="hidden" x-model="content" wire:model.live="{{ $key }}_content">
                        </div>
                    @endif
                </x-theme.section-card>
            @endforeach

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
