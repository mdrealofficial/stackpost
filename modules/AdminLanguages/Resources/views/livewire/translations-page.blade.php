<div class="space-y-7">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    @if ($errorMessage)
        <x-ui.alert variant="danger" :title="__('Action failed')" :description="$errorMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Language workspace')"
        :title="$language->name . ' · ' . __('Translation overrides')"
        :description="__('Fine-tune locale overrides for :locale.', ['locale' => $language->code])"
        :count="$items->count()"
        icon="fa-light fa-language"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-languages.index')" variant="outline" wire:navigate>
                {{ __('Back to languages') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.card>
        <form wire:submit="saveTranslations" class="space-y-5">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    :label="__('Translation search')"
                    :placeholder="__('Search translations by key or fallback...')"
                />
                <div class="flex items-center gap-2">
                    <x-ui.button type="button" variant="outline" wire:click="$refresh">{{ __('Search') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="$set('search', '')">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>

            <div class="space-y-4">
                @forelse ($items as $item)
                    @php
                        $field = $this->fieldName((string) $item['key']);
                    @endphp
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium text-zinc-950 dark:text-white">{{ $item['key'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $item['fallback'] }}</p>
                            </div>

                            @if ($item['has_override'])
                                <x-ui.badge variant="success">{{ __('Override') }}</x-ui.badge>
                            @endif
                        </div>

                        <div class="mt-4">
                            <x-ui.textarea
                                wire:model.defer="translations.{{ $field }}"
                                rows="3"
                            >{{ old("translations.{$field}", $translations[$field] ?? ($item['override'] ?? $item['value'])) }}</x-ui.textarea>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed p-10 text-center">
                        <x-ui.empty
                            icon="fa-light fa-magnifying-glass"
                            :title="__('No translations found.')"
                            :description="__('Try changing the search keyword or switch to another language.')"
                        />
                    </div>
                @endforelse
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <div>{{ $items->links() }}</div>
                <x-ui.button type="submit">
                    {{ __('Save changes') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
