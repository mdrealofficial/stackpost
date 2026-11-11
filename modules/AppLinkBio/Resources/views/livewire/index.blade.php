@php
    $limitValue = $stats['limit'];
    $usageLabel = $limitValue === null ? __('Unlimited') : $stats['total'].'/'.$limitValue;
    $pageCountLabel = trans_choice('{0} No pages|{1} :count page|[2,*] :count pages', $stats['total'], ['count' => $stats['total']]);
@endphp

<div class="portal-tools space-y-5 px-4 pb-8 pt-4 sm:px-5 xl:px-6">@include('partials.portal-tools-style')
<x-ui.sub-header compact :title="__('Link Bio')" :description="__('Create and manage the public pages that hold your important links.')"><x-slot:actions>
                <x-ui.dialog wire:key="link-bio-ai-dialog" :title="__('AI Bio Assistant')" :description="__('Generate a ready-to-edit bio page draft from a short description.')" width="md" dismissible>
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="outline" class="{{ $canCreate && $canUseAiBio ? '' : 'pointer-events-none opacity-50' }}">
                            <i class="fa-light fa-sparkles"></i>
                            {{ __('AI Bio') }}
                        </x-ui.button>
                    </x-slot:trigger>

                    <div class="space-y-4">
                        <x-ui.textarea wire:model.defer="aiBioBrief" :label="__('What should this bio page be about?')" :error="$errors->first('aiBioBrief')" rows="4" placeholder="{{ __('Example: Personal trainer in Hanoi, sells online coaching, wants visitors to book a consultation and follow Instagram.') }}"></x-ui.textarea>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.select wire:model.defer="aiBioTone" :label="__('Tone')">
                                <option value="professional">{{ __('Professional') }}</option>
                                <option value="friendly">{{ __('Friendly') }}</option>
                                <option value="sales">{{ __('Sales') }}</option>
                                <option value="educational">{{ __('Educational') }}</option>
                                <option value="bold">{{ __('Bold') }}</option>
                                <option value="casual">{{ __('Casual') }}</option>
                            </x-ui.select>
                            <x-ai.language-field wire:model.defer="aiBioLanguage" name="aiBioLanguage" :value="$aiBioLanguage" :label="__('Language')" :preferred="[]" />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="button" variant="outline" wire:click="generateAiBio" wire:loading.attr="disabled" wire:target="generateAiBio" class="{{ $canUseAiBio && ($aiBioCreditPreview['enough'] ?? true) ? '' : 'pointer-events-none opacity-50' }}">
                                <i class="fa-light fa-sparkles" wire:loading.remove wire:target="generateAiBio"></i>
                                <i class="fa-light fa-spinner-third animate-spin" wire:loading wire:target="generateAiBio"></i>
                                <span wire:loading.remove wire:target="generateAiBio">{{ __('Generate draft') }}</span>
                                <span wire:loading wire:target="generateAiBio">{{ __('Generating...') }}</span>
                            </x-ui.button>
                            <span class="inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background-color: var(--theme-surface-base);">
                                <i class="fa-light fa-coins" style="color: var(--theme-accent);"></i>
                                <span>
                                    {{ trans_choice(':count credit|:count credits', (int) ($aiBioCreditPreview['amount'] ?? 0), ['count' => number_format((int) ($aiBioCreditPreview['amount'] ?? 0))]) }}
                                    <span class="mx-1">·</span>
                                    {{ ($aiBioCreditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits left', ['credits' => number_format((int) ($aiBioCreditPreview['remaining'] ?? 0))]) }}
                                </span>
                            </span>
                        </div>

                        @if ($aiBioDraft !== [])
                            <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-accent-rgb),0.18); background-color: rgba(var(--theme-accent-rgb),0.05);">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('Draft preview') }}</p>
                                <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ data_get($aiBioDraft, 'title') }}</p>
                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ data_get($aiBioDraft, 'headline') }}</p>
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ data_get($aiBioDraft, 'description') }}</p>
                                <p class="mt-3 text-xs" style="color: var(--theme-muted-text-color);">{{ count((array) data_get($aiBioDraft, 'blocks', [])) }} {{ __('blocks will be created') }}</p>
                            </div>
                        @endif
                    </div>

                    <x-slot:footer>
                        <div class="flex flex-wrap justify-end gap-3">
                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="button" wire:click="createFromAiBioDraft" wire:loading.attr="disabled" wire:target="createFromAiBioDraft" class="{{ $aiBioDraft !== [] && $canCreate && $canUseAiBio ? '' : 'pointer-events-none opacity-50' }}">
                                <i class="fa-light fa-wand-magic-sparkles"></i>
                                {{ __('Create from draft') }}
                            </x-ui.button>
                        </div>
                    </x-slot:footer>
                </x-ui.dialog>
                <x-ui.button href="{{ route('portal.link-bio.templates') }}" variant="outline">
                    <i class="fa-light fa-swatchbook"></i>
                    {{ __('Templates') }}
                </x-ui.button>
                <x-ui.button href="{{ route('portal.link-bio.create') }}" class="{{ $canCreate ? '' : 'pointer-events-none opacity-50' }}">
                    <i class="fa-light fa-plus"></i>
                    {{ __('Create Link Bio') }}
                </x-ui.button>
            </x-slot:actions></x-ui.sub-header>
<div class="flex flex-wrap gap-4 text-sm" style="color:var(--theme-muted-text-color)"><span>{{ $pageCountLabel }}</span><span>{{ $stats['published'] }} {{ __('published') }}</span><span>{{ __('Page allowance') }}: {{ $usageLabel }}</span></div>

    <x-ui.card padding="sm" class="overflow-visible">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-end">
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Title, slug, headline...')" />
            <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all">{{ __('All') }}</option>
                <option value="published">{{ __('Published') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
            </x-ui.select>
        </div>

        @if ($search !== '' || $statusFilter !== 'all')
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($search !== '')
                    <x-ui.badge variant="neutral">{{ __('Search') }}: {{ $search }}</x-ui.badge>
                @endif
                @if ($statusFilter !== 'all')
                    <x-ui.badge variant="neutral">{{ __('Status') }}: {{ $statusFilter === 'published' ? __('Published') : __('Draft') }}</x-ui.badge>
                @endif
            </div>
        @endif
    </x-ui.card>

    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3 px-1">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Pages') }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Your bio pages') }}</h2>
            </div>
            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $stats['draft'] }} {{ __('drafts') }}</p>
        </div>

        @if ($pages->isEmpty())
            <x-ui.card>
                <x-ui.empty icon="fa-light fa-link-horizontal" :title="__('No bio pages yet')" :description="__('Create your first link bio page to start sharing profile links, products, or content blocks.')" />
            </x-ui.card>
        @else
            <div class="grid gap-x-4 gap-y-7 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($pages as $page)
                    <x-ui.card padding="none" class="group relative z-0 overflow-visible transition hover:z-20 hover:-translate-y-1 hover:shadow-[0_26px_60px_-40px_rgba(15,23,42,0.42)] focus-within:z-30">
                        <div class="relative aspect-[16/9] overflow-hidden rounded-t-[var(--theme-card-radius,1.15rem)]" style="@if($page->cover_url) background-image: linear-gradient(180deg, rgba(15,23,42,0.06), rgba(15,23,42,0.26)), url('{{ $page->cover_url }}'); background-size: cover; background-position: center; @elseif($page->backgroundImage() !== '') background-image: linear-gradient(rgba(15,23,42,{{ max(12, $page->backgroundOverlay()) / 100 }}), rgba(15,23,42,{{ max(18, $page->backgroundOverlay()) / 100 }})), url('{{ $page->backgroundImage() }}'); background-size: {{ $page->backgroundFit() === 'pattern' ? '180px auto' : $page->backgroundFit() }}; background-position: {{ $page->backgroundPosition() }}; background-repeat: {{ $page->backgroundFit() === 'pattern' ? 'repeat' : 'no-repeat' }}; @else background: {{ data_get($page->templateMeta(), 'preview') }}; @endif">
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-slate-950/32 to-transparent"></div>
                            <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                                <x-ui.badge variant="primary" class="px-2 py-0.5 text-[10px]" style="background-color: rgba(255,255,255,0.92); color: {{ $page->accent_color ?: 'var(--theme-accent)' }};">
                                    {{ data_get($page->templateMeta(), 'label') }}
                                </x-ui.badge>
                                <x-ui.badge :variant="$page->is_published ? 'success' : 'neutral'" class="px-2 py-0.5 text-[10px]">
                                    {{ $page->is_published ? __('Published') : __('Draft') }}
                                </x-ui.badge>
                            </div>
                        </div>

                        <div class="flex min-h-[11.25rem] flex-col p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $page->title }}</h3>
                                    <p class="mt-1 truncate text-sm" style="color: var(--theme-muted-text-color);">/{{ $page->slug }}</p>
                                </div>
                            </div>

                            <div class="mt-3 min-h-[3rem]">
                                @if ($page->headline)
                                    <p class="line-clamp-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $page->headline }}</p>
                                @endif
                            </div>

                            @php($pageAnalytics = $analytics[$page->id] ?? ['views' => 0, 'clicks' => 0, 'ctr' => 0])
                            <div class="mb-3 grid grid-cols-3 gap-2 rounded-[0.9rem] border px-3 py-2 text-center" style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 56%, transparent);">
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Views') }}</p>
                                    <p class="mt-0.5 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($pageAnalytics['views']) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Clicks') }}</p>
                                    <p class="mt-0.5 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($pageAnalytics['clicks']) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('CTR') }}</p>
                                    <p class="mt-0.5 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $pageAnalytics['ctr'] }}%</p>
                                </div>
                            </div>

                            <div class="mt-auto grid grid-cols-[minmax(0,1fr)_2.5rem_2.5rem] items-center gap-1 rounded-[0.95rem] border p-1" style="border-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
                                <a href="{{ route('link-bio.public.show', ['slug' => $page->slug]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 min-w-0 items-center justify-center gap-2 rounded-[0.75rem] bg-[var(--theme-accent)] px-3 text-sm font-semibold text-white shadow-[0_12px_26px_-18px_rgba(var(--theme-accent-rgb),0.5)] transition hover:-translate-y-px hover:opacity-95">
                                    <i class="fa-light fa-arrow-up-right-from-square"></i>
                                    <span class="truncate">{{ __('Open') }}</span>
                                </a>
                                <a href="{{ route('portal.link-bio.edit', ['page' => $page->id]) }}" title="{{ __('Edit') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.75rem] text-sm transition hover:-translate-y-px" style="color: var(--theme-header-text-color); background-color: var(--theme-surface-base); box-shadow: 0 1px 2px rgba(15,23,42,0.08);">
                                    <i class="fa-light fa-pen-to-square"></i>
                                </a>
                                <x-ui.dropdown-menu align="right" width="auto" class="shrink-0">
                                    <x-slot:trigger>
                                        <button type="button" title="{{ __('More') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.75rem] text-sm transition hover:-translate-y-px" style="color: var(--theme-header-text-color); background-color: var(--theme-surface-base); box-shadow: 0 1px 2px rgba(15,23,42,0.08);">
                                            <i class="fa-light fa-ellipsis"></i>
                                        </button>
                                    </x-slot:trigger>

                                    <x-ui.dropdown-menu-item href="{{ route('portal.link-bio.templates', ['page' => $page->id]) }}" icon="fa-light fa-swatchbook">
                                        {{ __('Template') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item type="button" wire:click="duplicate({{ $page->id }})" icon="fa-light fa-copy">
                                        {{ __('Duplicate') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item type="button" wire:click="togglePublish({{ $page->id }})" icon="fa-light {{ $page->is_published ? 'fa-eye-slash' : 'fa-eye' }}">
                                        {{ $page->is_published ? __('Unpublish') : __('Publish') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dialog wire:key="link-bio-qr-dialog-{{ $page->id }}" :title="__('QR code')" :description="__('Scan to open the public bio page.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <button type="button" class="flex w-full items-center gap-3 whitespace-nowrap rounded-[0.8rem] px-3 py-2 text-left text-[15px] font-medium leading-6 transition hover:bg-slate-50" style="color: var(--theme-header-text-color);">
                                                <i class="fa-light fa-qrcode text-[14px]"></i>
                                                <span class="min-w-0 flex-1 truncate">{{ __('QR code') }}</span>
                                            </button>
                                        </x-slot:trigger>
                                        <div class="space-y-3 text-center">
                                            <div class="mx-auto inline-flex rounded-[1rem] border bg-white p-3" style="border-color: var(--theme-border-color);">
                                                {!! $qrCodes[$page->id] ?? '' !!}
                                            </div>
                                            <p class="break-all text-sm" style="color: var(--theme-muted-text-color);">{{ route('link-bio.public.show', ['slug' => $page->slug]) }}</p>
                                        </div>
                                    </x-ui.dialog>
                                    <x-ui.dropdown-menu-divider />
                                    <x-ui.dialog wire:key="link-bio-delete-dialog-{{ $page->id }}" :title="__('Delete this bio page?')" :description="__('This permanently removes the selected bio page and its public URL. This action cannot be undone.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <button type="button" class="flex w-full items-center gap-3 whitespace-nowrap rounded-[0.8rem] px-3 py-2 text-left text-[15px] font-medium leading-6 transition hover:bg-rose-50" style="color: var(--theme-danger-color);">
                                                <i class="fa-light fa-trash-can text-[14px]"></i>
                                                <span class="min-w-0 flex-1 truncate">{{ __('Delete') }}</span>
                                            </button>
                                        </x-slot:trigger>
                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                <x-ui.button type="button" variant="danger" wire:click="delete({{ $page->id }})" x-on:click="open = false">
                                                    <i class="fa-light fa-trash-can"></i>
                                                    {{ __('Delete') }}
                                                </x-ui.button>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                </x-ui.dropdown-menu>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </section>
</div>
