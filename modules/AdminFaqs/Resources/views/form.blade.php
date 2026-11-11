@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit FAQ') : __('Create FAQ')])
    <div class="space-y-6">
        <x-ui.sub-header
            :eyebrow="__('Settings')"
            :title="$isEditing ? __('Edit FAQ') : __('Create FAQ')"
            :description="$isEditing ? __('Update an existing answer, slug, and publication status for this FAQ item.') : __('Write a new frequently asked question and answer for your public pages.')"
        >
            <x-slot:actions>
                <x-ui.button href="{{ route('admin-faqs.index') }}" variant="outline" wire:navigate>
                    {{ __('Back') }}
                </x-ui.button>
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="mx-auto max-w-[860px] pb-8">
            

            <form method="POST" action="{{ $isEditing ? route('admin-faqs.update', $faq) : route('admin-faqs.store') }}" class="space-y-4">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif

                <x-ui.form-section :title="__('FAQ information')">
                    <x-ui.choice-group
                        name="status"
                        :label="__('Status')"
                        :value="(int) old('status', (int) ($faq->status ?? true))"
                        :error="$errors->first('status')"
                        :options="[
                            ['value' => 1, 'label' => __('Enable')],
                            ['value' => 0, 'label' => __('Disable')],
                        ]"
                    />

                    <x-ui.input name="title" :label="__('Title')" :value="old('title', $faq->title)" :error="$errors->first('title')" required autofocus />
                    <x-ui.input name="slug" :label="__('Slug')" :value="old('slug', $faq->slug)" :error="$errors->first('slug')" :help="__('Leave empty to generate from the title.')" />
                    <x-ui.textarea name="content" :label="__('Content')" :error="$errors->first('content')" rows="10">{{ old('content', $faq->content) }}</x-ui.textarea>
                </x-ui.form-section>

                <div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-[0.5rem] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background-color: #1f2430;">
                        {{ __('Save changes') }}
                    </button>
                </div>
            </form>

            @if ($isEditing)
                <div class="mt-4 overflow-hidden rounded-[0.75rem] border shadow-[0_2px_10px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                    <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                        <h3 class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ __('Danger zone') }}</h3>
                    </div>
                    <div class="space-y-4 px-6 py-6">
                        <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Delete this FAQ if it should no longer appear in your public knowledge base.') }}</p>
                        <x-ui.dialog :title="__('Delete FAQ?')" :description="__('This permanently removes the selected FAQ entry.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger">
                                    {{ __('Delete FAQ') }}
                                </x-ui.button>
                            </x-slot:trigger>

                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                        {{ __('Cancel') }}
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('admin-faqs.destroy', $faq) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="danger">
                                            {{ __('Delete') }}
                                        </x-ui.button>
                                    </form>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endcomponent
