@component(theme_view('layouts.app', 'app'), ['title' => __('FAQs')])
    @php
        $requestedModal = request()->string('faq_modal')->toString();
        $requestedFaqId = request()->input('faq');
        $createFaq = new \Modules\AdminFaqs\Models\Faq();

        if ($errors->any() && old('_faq_modal') === 'create') {
            $createFaq->title = old('title');
            $createFaq->slug = old('slug');
            $createFaq->content = old('content');
            $createFaq->status = old('status', 1);
        }

        $openCreateModal = ($errors->any() && old('_faq_modal') === 'create') || $requestedModal === 'create';
    @endphp

    <div class="space-y-6">
        

        <x-ui.card class="overflow-hidden border-none shadow-[0_30px_80px_-48px_rgba(var(--theme-accent-rgb),0.45)]" style="background:
            radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.22), transparent 38%),
            linear-gradient(135deg, color-mix(in srgb, var(--theme-accent) 10%, var(--theme-surface-base)) 0%, var(--theme-surface-base) 58%, color-mix(in srgb, var(--theme-header-text-color) 6%, var(--theme-surface-base)) 100%);
        ">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-xl shadow-[0_18px_38px_-24px_rgba(var(--theme-accent-rgb),0.55)]" style="background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);">
                        <i class="fa-light fa-messages-question"></i>
                    </span>
                    <div class="min-w-0">
                        <x-ui.sub-header
                            :eyebrow="__('Settings')"
                            :title="__('Frequently Asked Questions')"
                            :description="__('Manage common customer questions and answers shown on your public-facing pages.')"
                            :count="$summary['total']"
                        />
                    </div>
                </div>

                <x-ui.modal
                    width="xl"
                    :title="__('Create FAQ')"
                    :description="__('Write a new frequently asked question and answer for your public pages.')"
                    :initially-open="$openCreateModal"
                >
                    <x-slot:trigger>
                        <x-ui.button type="button">
                            <i class="fa-light fa-plus"></i>
                            {{ __('Create FAQ') }}
                        </x-ui.button>
                    </x-slot:trigger>

                    <form id="create-faq-modal-form" method="POST" action="{{ route('admin-faqs.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="_faq_modal" value="create">

                        @include('adminfaqs::partials.fields', [
                            'faq' => $createFaq,
                        ])
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="outline" x-on:click="open = false">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" form="create-faq-modal-form">
                            <i class="fa-light fa-floppy-disk"></i>
                            {{ __('Save changes') }}
                        </x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            </div>
        </x-ui.card>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="group relative overflow-hidden border-white/60 p-0 shadow-[0_28px_60px_-34px_rgba(15,23,42,0.22)]">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[rgba(var(--theme-accent-rgb),0.65)] to-transparent"></div>
                <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full blur-3xl" style="background-color: rgba(var(--theme-accent-rgb), 0.16);"></div>
                <div class="relative space-y-6 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-3">
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em]" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ __('Total') }}</span>
                            <div class="flex items-end gap-2">
                                <p class="text-4xl font-semibold leading-none tracking-[-0.07em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['total']) }}</p>
                                <span class="pb-1 text-xs font-medium uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('entries') }}</span>
                            </div>
                        </div>
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[1.15rem] border text-base shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-sm transition-transform duration-300 group-hover:scale-105" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                            <i class="fa-light fa-layer-group"></i>
                        </span>
                    </div>
                    <div class="space-y-3">
                        <p class="max-w-[24ch] text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('All FAQ entries currently stored.') }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full" style="background-color: rgba(var(--theme-accent-rgb), 0.08);">
                            <div class="h-full rounded-full" style="width: 100%; background: linear-gradient(90deg, rgba(var(--theme-accent-rgb), 0.42), rgba(var(--theme-accent-rgb), 0.92));"></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="group relative overflow-hidden border-emerald-200/70 p-0 shadow-[0_28px_60px_-34px_rgba(16,185,129,0.22)] dark:border-emerald-500/20">
                <div class="absolute inset-x-0 top-0 h-px" style="background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.8), transparent);"></div>
                <div class="absolute -right-8 -top-10 h-28 w-28 rounded-full bg-emerald-400/20 blur-3xl"></div>
                <div class="relative space-y-6 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-3">
                            <span class="inline-flex items-center rounded-full border border-emerald-500/15 bg-emerald-500/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-300">{{ __('Enabled') }}</span>
                            <div class="flex items-end gap-2">
                                <p class="text-4xl font-semibold leading-none tracking-[-0.07em] text-emerald-600 dark:text-emerald-300">{{ number_format($summary['enabled']) }}</p>
                                <span class="pb-1 text-xs font-medium uppercase tracking-[0.18em] text-emerald-600/70 dark:text-emerald-300/70">{{ __('live') }}</span>
                            </div>
                        </div>
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[1.15rem] border border-emerald-500/15 bg-emerald-500/10 text-base text-emerald-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-sm transition-transform duration-300 group-hover:scale-105 dark:text-emerald-300">
                            <i class="fa-light fa-circle-check"></i>
                        </span>
                    </div>
                    <div class="space-y-3">
                        <p class="max-w-[24ch] text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('FAQs visible for public consumption.') }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-emerald-500/10">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 dark:from-emerald-300 dark:to-emerald-500" style="width: {{ max(8, $summary['total'] > 0 ? round(($summary['enabled'] / $summary['total']) * 100) : 8) }}%;"></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="group relative overflow-hidden border-slate-200/80 p-0 shadow-[0_28px_60px_-34px_rgba(71,85,105,0.18)] dark:border-slate-700/70">
                <div class="absolute inset-x-0 top-0 h-px" style="background: linear-gradient(90deg, transparent, rgba(100, 116, 139, 0.75), transparent);"></div>
                <div class="absolute -right-8 -top-10 h-28 w-28 rounded-full bg-slate-400/15 blur-3xl dark:bg-slate-500/10"></div>
                <div class="relative space-y-6 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-3">
                            <span class="inline-flex items-center rounded-full border border-slate-300/70 bg-slate-500/5 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:border-slate-700 dark:text-slate-300">{{ __('Disabled') }}</span>
                            <div class="flex items-end gap-2">
                                <p class="text-4xl font-semibold leading-none tracking-[-0.07em] text-slate-700 dark:text-slate-200">{{ number_format($summary['disabled']) }}</p>
                                <span class="pb-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">{{ __('hidden') }}</span>
                            </div>
                        </div>
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[1.15rem] border border-slate-300/70 bg-slate-500/5 text-base text-slate-500 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-sm transition-transform duration-300 group-hover:scale-105 dark:border-slate-700 dark:text-slate-300">
                            <i class="fa-light fa-eye-slash"></i>
                        </span>
                    </div>
                    <div class="space-y-3">
                        <p class="max-w-[24ch] text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Draft or hidden FAQ entries.') }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-gradient-to-r from-slate-400 to-slate-600 dark:from-slate-500 dark:to-slate-300" style="width: {{ max(8, $summary['total'] > 0 ? round(($summary['disabled'] / $summary['total']) * 100) : 8) }}%;"></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-sm" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                    <i class="fa-light fa-sliders"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Filter FAQs') }}</p>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search by content, narrow by status, and review entries faster.') }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin-faqs.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Title, slug, content...')" />

                <x-ui.select name="status" :label="__('Status')">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="1" @selected((string) $filters['status'] === '1')>{{ __('Enabled') }}</option>
                    <option value="0" @selected((string) $filters['status'] === '0')>{{ __('Disabled') }}</option>
                </x-ui.select>

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">
                        <i class="fa-light fa-filter"></i>
                        {{ __('Filter') }}
                    </x-ui.button>
                    <x-ui.button href="{{ route('admin-faqs.index') }}" variant="outline" wire:navigate>
                        <i class="fa-light fa-rotate-right"></i>
                        {{ __('Reset') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($faqs as $faq)
                @php
                    $editModalOpen = (($errors->any() && old('_faq_modal') === 'edit' && (string) old('_faq_id') === (string) $faq->id)
                        || ($requestedModal === 'edit' && (string) $requestedFaqId === (string) $faq->id));
                    $editFaq = clone $faq;

                    if ($errors->any() && old('_faq_modal') === 'edit' && (string) old('_faq_id') === (string) $faq->id) {
                        $editFaq->title = old('title');
                        $editFaq->slug = old('slug');
                        $editFaq->content = old('content');
                        $editFaq->status = old('status', $faq->status);
                    }
                @endphp
                <x-ui.card class="space-y-4 transition-transform duration-200 hover:-translate-y-1">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-base" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                <i class="fa-light fa-circle-question"></i>
                            </span>
                            <div class="min-w-0">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $faq->title }}</p>
                            <p class="truncate text-xs" style="color: var(--theme-muted-text-color);">/{{ $faq->slug }}</p>
                            </div>
                        </div>
                        <x-ui.badge :variant="$faq->statusVariant()">{{ $faq->statusLabel() }}</x-ui.badge>
                    </div>

                    <p class="min-h-[4.5rem] rounded-[1rem] border px-4 py-3 text-sm leading-6" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent); color: var(--theme-muted-text-color);">
                        {{ $faq->contentPreview() }}
                    </p>

                    <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                        <p class="inline-flex items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                            <i class="fa-light fa-clock-rotate-left"></i>
                            {{ $faq->createdAtFormatted() ?: __('N/A') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <x-ui.modal
                                width="xl"
                                :title="__('Edit FAQ')"
                                :description="__('Update an existing answer, slug, and publication status for this FAQ item.')"
                                :initially-open="$editModalOpen"
                            >
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="outline" size="sm">
                                        <i class="fa-light fa-pen-to-square"></i>
                                        {{ __('Edit') }}
                                    </x-ui.button>
                                </x-slot:trigger>

                                <form id="edit-faq-modal-form-{{ $faq->id }}" method="POST" action="{{ route('admin-faqs.update', $faq) }}" class="space-y-5">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_faq_modal" value="edit">
                                    <input type="hidden" name="_faq_id" value="{{ $faq->id }}">

                                    @include('adminfaqs::partials.fields', [
                                        'faq' => $editFaq,
                                    ])
                                </form>

                                <div class="rounded-[0.95rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-danger-color) 20%, var(--theme-border-color)); background-color: color-mix(in srgb, var(--theme-danger-color) 4%, var(--theme-surface-base));">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Danger zone') }}</p>
                                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Delete this FAQ if it should no longer appear in your public knowledge base.') }}</p>
                                        </div>

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

                                <x-slot:footer>
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                        {{ __('Cancel') }}
                                    </x-ui.button>
                                    <x-ui.button type="submit" form="edit-faq-modal-form-{{ $faq->id }}">
                                        <i class="fa-light fa-floppy-disk"></i>
                                        {{ __('Save changes') }}
                                    </x-ui.button>
                                </x-slot:footer>
                            </x-ui.modal>

                            <x-ui.dialog :title="__('Delete FAQ?')" :description="__('This permanently removes the selected FAQ entry.')" width="sm" dismissible>
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="danger" size="sm">
                                        <i class="fa-light fa-trash-can"></i>
                                        {{ __('Delete') }}
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
                </x-ui.card>
            @empty
                <x-ui.card class="md:col-span-2 xl:col-span-3">
                    <x-ui.empty
                        class="py-12"
                        icon="fa-light fa-messages-question"
                        :title="__('No FAQs found.')"
                        :description="__('Try broadening your filters or create your first FAQ entry.')"
                    />
                </x-ui.card>
            @endforelse
        </div>

        @if ($faqs->hasPages())
            <div class="rounded-[0.85rem] border px-6 py-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                {{ $faqs->links() }}
            </div>
        @endif
    </div>
@endcomponent
