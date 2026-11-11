<div
    class="portal-tools space-y-5" x-bind:class="mobilePreview ? 'bio-mobile-preview' : 'bio-mobile-edit'"
    x-data="{
        mobilePreview: false, activeSidebarTab: 'basic',
        activeBlockIndex: @js($activeBlockIndex),
        draggingBlockType: null,
        draggingBlockIndex: null,
        dragOverBlockIndex: null,
        previewDropActive: false,
        blockOrder: @js(array_keys($blocks)),
        setSidebarTab(tab) {
            this.activeSidebarTab = tab;
        },
        syncBlockOrder() {
            const count = Number($wire.get('blocks')?.length || 0);
            this.blockOrder = Array.from({ length: count }, (_, index) => index);
        },
        selectBlockFast(index) {
            this.activeBlockIndex = index;
            $wire.selectBlock(index);
        },
        moveBlockFast(index, direction) {
            const currentPosition = this.blockOrder.indexOf(index);
            const nextPosition = currentPosition + direction;

            if (currentPosition < 0 || nextPosition < 0 || nextPosition >= this.blockOrder.length) {
                return;
            }

            this.blockOrder.splice(currentPosition, 1);
            this.blockOrder.splice(nextPosition, 0, index);
            this.activeBlockIndex = index;

            if (direction < 0) {
                $wire.moveBlockUp(index).then(() => {
                    this.syncBlockOrder();
                    this.activeBlockIndex = Number($wire.get('activeBlockIndex') ?? this.activeBlockIndex);
                });
                return;
            }

            $wire.moveBlockDown(index).then(() => {
                this.syncBlockOrder();
                this.activeBlockIndex = Number($wire.get('activeBlockIndex') ?? this.activeBlockIndex);
            });
        },
        startBlockDrag(type) {
            this.draggingBlockType = type;
        },
        endBlockDrag() {
            this.draggingBlockType = null;
            this.draggingBlockIndex = null;
            this.dragOverBlockIndex = null;
            this.previewDropActive = false;
        },
        dropBlock() {
            if (!this.draggingBlockType) {
                return;
            }

            $wire.addBlock(this.draggingBlockType);
            this.endBlockDrag();
        },
        startBlockReorder(index) {
            this.draggingBlockIndex = index;
            this.dragOverBlockIndex = index;
        },
        dropBlockReorder(targetIndex) {
            if (this.draggingBlockIndex === null || this.draggingBlockIndex === targetIndex) {
                this.endBlockDrag();
                return;
            }

            const from = this.blockOrder.indexOf(this.draggingBlockIndex);
            const to = this.blockOrder.indexOf(targetIndex);

            if (from < 0 || to < 0) {
                this.endBlockDrag();
                return;
            }

            const [moved] = this.blockOrder.splice(from, 1);
            this.blockOrder.splice(to, 0, moved);
            this.activeBlockIndex = moved;

            $wire.reorderBlocks(this.blockOrder).then(() => {
                this.syncBlockOrder();
                this.activeBlockIndex = Number($wire.get('activeBlockIndex') ?? this.activeBlockIndex);
            });

            this.endBlockDrag();
        }
    }"
    x-on:image-picker:change.window="
        const imagePickerName = String($event.detail?.name || '');
        const imagePickerValue = String($event.detail?.value || '');

        if (imagePickerName === 'avatar_url') {
            $wire.set('avatarUrl', imagePickerValue);
        }

        if (imagePickerName === 'cover_url') {
            $wire.set('coverUrl', imagePickerValue);
        }

        if (imagePickerName === 'background_url') {
            $wire.set('backgroundUrl', imagePickerValue);
        }

        if (imagePickerName.startsWith('block_item_image_')) {
            const parts = imagePickerName.split('_');
            const blockIndex = Number(parts[3] ?? -1);
            const itemIndex = Number(parts[4] ?? -1);

            if (Number.isInteger(blockIndex) && blockIndex >= 0 && Number.isInteger(itemIndex) && itemIndex >= 0) {
                $wire.set(`blocks.${blockIndex}.items.${itemIndex}.image`, imagePickerValue);
            }
        }
    "
>
@include('partials.portal-tools-style')
    <x-ui.card padding="sm" class="overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-[1rem] sm:inline-flex" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                    <i class="fa-light fa-link-horizontal"></i>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-accent);">{{ $isEditing ? __('Editing') : __('New bio') }}</span>
                        <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs" style="border-color: rgba(var(--theme-border-color-rgb),0.62); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                            <i class="fa-light fa-link-simple text-[11px]"></i>
                            <span class="truncate">/{{ $slug ?: __('link-bio') }}</span>
                        </span>
                    </div>
                    <h1 class="mt-1 truncate text-[1.15rem] font-semibold leading-7 tracking-[-0.025em]" style="color: var(--theme-header-text-color);">{{ $title ?: __('Customizer workspace') }}</h1>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button href="{{ route('portal.link-bio.index') }}" variant="outline" size="sm">
                    <i class="fa-light fa-arrow-left"></i>
                    {{ __('Back') }}
                </x-ui.button>
                <x-ui.button href="{{ $templatesUrl }}" variant="outline" size="sm">
                    <i class="fa-light fa-swatchbook"></i>
                    {{ __('Templates') }}
                </x-ui.button>
                <x-ui.button type="button" wire:click="saveDraft" variant="outline" size="sm">
                    <i class="fa-light fa-floppy-disk"></i>
                    {{ __('Save draft') }}
                </x-ui.button><x-ui.button type="button" wire:click="publishPage" size="sm">{{ $isPublished ? __('Update published page') : __('Publish') }}</x-ui.button>
            </div>
        </div>
    </x-ui.card>



    <div class="bio-mobile-switch"><x-ui.button variant="outline" x-on:click="mobilePreview=false" x-bind:aria-pressed="!mobilePreview">{{ __('Edit') }}</x-ui.button><x-ui.button variant="outline" x-on:click="mobilePreview=true" x-bind:aria-pressed="mobilePreview">{{ __('Preview') }}</x-ui.button></div><div class="grid gap-5 xl:grid-cols-[410px_minmax(0,1fr)]">
        <aside class="bio-editor min-w-0">
            <x-ui.card padding="none" class="overflow-hidden xl:sticky xl:top-24">
                <div class="border-b p-3" style="border-color: var(--theme-border-color);">
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" x-on:click="setSidebarTab('basic')" x-bind:class="activeSidebarTab === 'basic' ? 'shadow-sm' : ''" class="rounded-[0.85rem] px-3 py-2.5 text-xs font-semibold transition" x-bind:style="activeSidebarTab === 'basic' ? 'background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'color: var(--theme-muted-text-color);'">
                            <i class="fa-light fa-id-card mr-1.5"></i>{{ __('Info') }}
                        </button>
                        <button type="button" x-on:click="setSidebarTab('blocks')" x-bind:class="activeSidebarTab === 'blocks' ? 'shadow-sm' : ''" class="rounded-[0.85rem] px-3 py-2.5 text-xs font-semibold transition" x-bind:style="activeSidebarTab === 'blocks' ? 'background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'color: var(--theme-muted-text-color);'">
                            <i class="fa-light fa-layer-group mr-1.5"></i>{{ __('Blocks') }}
                        </button>
                        <button type="button" x-on:click="setSidebarTab('templates')" x-bind:class="activeSidebarTab === 'templates' ? 'shadow-sm' : ''" class="rounded-[0.85rem] px-3 py-2.5 text-xs font-semibold transition" x-bind:style="activeSidebarTab === 'templates' ? 'background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'color: var(--theme-muted-text-color);'">
                            <i class="fa-light fa-swatchbook mr-1.5"></i>{{ __('Style') }}
                        </button>
                    </div>
                </div>

                <div class="max-h-none overflow-visible p-4 xl:max-h-[calc(100vh-16rem)] xl:overflow-y-auto">
                    <div x-show="activeSidebarTab === 'basic'" x-transition.opacity class="space-y-4">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Basic info') }}</p>
                            <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Identity, media, visual controls, and publish state.') }}</p>
                        </div>

                        <x-ui.input wire:model.live.debounce.300ms="title" :label="__('Title')" :error="$errors->first('title')" />
                        <x-ui.input wire:model.defer="slug" :label="__('Slug')" :error="$errors->first('slug')" />
                        <x-ui.input wire:model.live.debounce.300ms="headline" :label="__('Headline')" :error="$errors->first('headline')" />
                        <x-ui.emoji-textarea wire:model.live.debounce.300ms="description" :label="__('Description')" :error="$errors->first('description')" trigger-position="inside-top-right" picker-align="right" rows="4">{{ $description }}</x-ui.emoji-textarea>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <x-ui.color-picker wire:model.live="accentColor" :label="__('Accent color')" :value="$accentColor" :error="$errors->first('accentColor')" />
                            <x-ui.select wire:model.live="contentAlign" :label="__('Alignment')">
                                <option value="left">{{ __('Left') }}</option>
                                <option value="center">{{ __('Center') }}</option>
                            </x-ui.select>
                            <x-ui.select wire:model.live="avatarStyle" :label="__('Avatar')">
                                <option value="circle">{{ __('Circle') }}</option>
                                <option value="rounded">{{ __('Rounded') }}</option>
                                <option value="square">{{ __('Square') }}</option>
                            </x-ui.select>
                            <x-ui.select wire:model.live="buttonStyle" :label="__('Buttons')">
                                <option value="pill">{{ __('Pill') }}</option>
                                <option value="rounded">{{ __('Rounded') }}</option>
                                <option value="square">{{ __('Square') }}</option>
                            </x-ui.select>
                        </div>

                        <div class="space-y-2">
                            <x-ui.input wire:model.live.debounce.300ms="brandingText" :label="__('Footer branding text')" :error="$errors->first('brandingText')" :disabled="! $canCustomizeBranding" />
                            @unless ($canCustomizeBranding)
                                <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-lock mr-1"></i>
                                    {{ __('Your current plan keeps Link Bio branding on public pages.') }}
                                </p>
                            @endunless
                        </div>
                        <x-ui.image-picker name="avatar_url" context="portal" value-field="url" :value="$avatarUrl" :preview="$avatarUrl" :label="__('Avatar image')" :error="$errors->first('avatarUrl')" :button-label="__('Choose avatar')" :dialog-title="__('Choose avatar image')" :dialog-description="__('Select an image from Files.')" />
                        <x-ui.image-picker name="cover_url" context="portal" value-field="url" :value="$coverUrl" :preview="$coverUrl" :label="__('Cover image')" :error="$errors->first('coverUrl')" :button-label="__('Choose cover')" :dialog-title="__('Choose cover image')" :dialog-description="__('Select an image from Files.')" />

                        <x-ui.surface-card padding="sm" accent="success" featured>
                            <p class="portal-tool-notice">{{ __('Save a private draft, or use Publish when the page is ready.') }}</p>
                        </x-ui.surface-card>
                    </div>

                    <div x-show="activeSidebarTab === 'blocks'" x-transition.opacity class="flex flex-col gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Edit content') }}</p>
                                <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Select a section below, then edit its fields here.') }}</p>
                            </div>
                            @if ($activeBlock)
                                <x-ui.badge variant="primary"><span x-text="'#' + (activeBlockIndex + 1)">#{{ $activeBlockIndex + 1 }}</span></x-ui.badge>
                            @endif
                        </div>

                        <div x-data="{ addBlocksOpen: false }" class="order-3 rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <button type="button" class="flex w-full items-center justify-between gap-3 text-left" x-on:click="addBlocksOpen = ! addBlocksOpen">
                                <span>
                                    <span class="block text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Add a block') }}</span>
                                    <span class="mt-1 block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Add only when you need a new section.') }}</span>
                                </span>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border text-sm transition" :class="{ 'rotate-45': addBlocksOpen }" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);">
                                    <i class="fa-light fa-plus"></i>
                                </span>
                            </button>

                            <div x-cloak x-show="addBlocksOpen" x-transition.opacity class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ([
                                    'links' => ['label' => __('Link'), 'icon' => 'fa-light fa-link'],
                                    'video' => ['label' => __('Video'), 'icon' => 'fa-light fa-circle-play'],
                                    'social' => ['label' => __('Social'), 'icon' => 'fa-light fa-share-nodes'],
                                    'header' => ['label' => __('Header'), 'icon' => 'fa-light fa-heading'],
                                    'contact' => ['label' => __('Contact'), 'icon' => 'fa-light fa-address-card'],
                                    'gallery' => ['label' => __('Gallery'), 'icon' => 'fa-light fa-images'],
                                    'embed' => ['label' => __('Embed'), 'icon' => 'fa-light fa-code'],
                                    'faq' => ['label' => __('FAQ'), 'icon' => 'fa-light fa-circle-question'],
                                    'product' => ['label' => __('Product'), 'icon' => 'fa-light fa-bag-shopping'],
                                ] as $blockType => $blockMeta)
                                    <button
                                        wire:click="addBlock('{{ $blockType }}')"
                                        type="button"
                                        draggable="true"
                                        x-on:click="addBlocksOpen = false"
                                        x-on:dragstart="startBlockDrag('{{ $blockType }}'); $event.dataTransfer.effectAllowed = 'copy'; $event.dataTransfer.setData('text/plain', '{{ $blockType }}')"
                                        x-on:dragend="endBlockDrag()"
                                        class="flex cursor-grab items-center gap-2 rounded-[0.8rem] border px-3 py-2 text-left text-xs font-semibold transition hover:-translate-y-0.5 active:cursor-grabbing"
                                        style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: var(--theme-surface-soft);"
                                        title="{{ __('Drag to preview or click to add') }}"
                                    >
                                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem]" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);"><i class="{{ $blockMeta['icon'] }}"></i></span>
                                        <span class="truncate">{{ $blockMeta['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <div x-cloak x-show="addBlocksOpen" x-transition.opacity class="mt-4 border-t pt-3" style="border-color: var(--theme-border-color);">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Industry presets') }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($blockPresets as $presetKey => $preset)
                                        <button
                                            type="button"
                                            wire:click="applyBlockPreset('{{ $presetKey }}')"
                                            x-on:click="addBlocksOpen = false"
                                            class="flex min-w-0 items-center gap-2 rounded-[0.8rem] border px-3 py-2 text-left text-xs font-semibold transition hover:-translate-y-0.5"
                                            style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: var(--theme-surface-base);"
                                        >
                                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem]" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);"><i class="{{ $preset['icon'] }}"></i></span>
                                            <span class="truncate">{{ $preset['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="order-2 flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-3 px-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Page blocks') }}</p>
                                <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ count($blocks) }}</span>
                            </div>
                            @foreach ($blocks as $blockIndex => $block)
                                @php
                                    $blockClicks = collect((array) data_get($blockAnalytics, 'blocks.'.$blockIndex.'.items', []))->sum(fn ($itemStats) => (int) data_get($itemStats, 'clicks', 0));
                                @endphp
                                <div
                                    wire:key="link-bio-block-row-{{ $blockIndex }}-{{ data_get($block, 'type', 'block') }}"
                                    draggable="true"
                                    x-on:dragstart="startBlockReorder({{ $blockIndex }}); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', 'block-{{ $blockIndex }}')"
                                    x-on:dragover.prevent="dragOverBlockIndex = {{ $blockIndex }}; $event.dataTransfer.dropEffect = 'move'"
                                    x-on:drop.prevent="dropBlockReorder({{ $blockIndex }})"
                                    x-on:dragend="endBlockDrag()"
                                    x-bind:class="{ 'opacity-60': draggingBlockIndex === {{ $blockIndex }}, 'ring-2 ring-offset-1': dragOverBlockIndex === {{ $blockIndex }} && draggingBlockIndex !== null }"
                                    class="cursor-grab rounded-[0.9rem] border p-1.5 transition-colors active:cursor-grabbing"
                                    x-bind:style="`order: ${blockOrder.indexOf({{ $blockIndex }})}; ` + (activeBlockIndex === {{ $blockIndex }}
                                        ? 'border-color: rgba(var(--theme-accent-rgb),0.32); background-color: rgba(var(--theme-accent-rgb),0.08);'
                                        : 'border-color: var(--theme-border-color); background-color: var(--theme-surface-base);')"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <button x-on:click="selectBlockFast({{ $blockIndex }})" type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-[0.75rem] px-2 py-1.5 text-left transition hover:bg-slate-900/5">
                                            <span
                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] text-[11px] font-semibold transition-colors"
                                                x-bind:style="activeBlockIndex === {{ $blockIndex }}
                                                    ? 'background-color: var(--theme-accent); color: #fff;'
                                                    : 'background-color: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);'"
                                            >{{ $blockIndex + 1 }}</span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ data_get($block, 'title') ?: str((string) data_get($block, 'type', 'block'))->headline() }}</span>
                                                <span class="mt-0.5 block text-xs" style="color: var(--theme-muted-text-color);">{{ str((string) data_get($block, 'type', 'block'))->headline() }}@if($blockClicks > 0) · {{ $blockClicks }} {{ __('clicks') }}@endif</span>
                                            </span>
                                        </button>
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <x-ui.button type="button" variant="outline" size="sm" x-on:click="moveBlockFast({{ $blockIndex }}, -1)" class="h-8 w-8 px-0"><i class="fa-light fa-arrow-up"></i></x-ui.button>
                                            <x-ui.button type="button" variant="outline" size="sm" x-on:click="moveBlockFast({{ $blockIndex }}, 1)" class="h-8 w-8 px-0"><i class="fa-light fa-arrow-down"></i></x-ui.button>
                                            <x-ui.button type="button" variant="outline" size="sm" wire:click.stop="duplicateBlock({{ $blockIndex }})" class="h-8 w-8 px-0"><i class="fa-light fa-copy"></i></x-ui.button>
                                            <x-ui.button type="button" variant="danger" size="sm" wire:click="removeBlock({{ $blockIndex }})" class="h-8 w-8 px-0"><i class="fa-light fa-trash-can"></i></x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($activeBlock)
                            <x-ui.surface-card padding="sm" class="order-1 space-y-4">
                                @php
                                    $activeBlockType = (string) data_get($activeBlock, 'type', 'links');
                                    $itemsLabel = match ($activeBlockType) {
                                        'links' => __('Links'),
                                        'social' => __('Social profiles'),
                                        'contact' => __('Contact methods'),
                                        'gallery' => __('Gallery cards'),
                                        'faq' => __('Questions'),
                                        'product' => __('Products'),
                                        default => __('Items'),
                                    };
                                    $addItemLabel = match ($activeBlockType) {
                                        'links' => __('Add link'),
                                        'social' => __('Add profile'),
                                        'contact' => __('Add contact'),
                                        'gallery' => __('Add card'),
                                        'faq' => __('Add question'),
                                        'product' => __('Add product'),
                                        default => __('Add item'),
                                    };
                                    $blockSettingsTitle = match ($activeBlockType) {
                                        'links' => __('Links block'),
                                        'social' => __('Social block'),
                                        'contact' => __('Contact block'),
                                        'gallery' => __('Gallery block'),
                                        'faq' => __('FAQ block'),
                                        'product' => __('Product block'),
                                        'header' => __('Header block'),
                                        'video' => __('Video block'),
                                        'embed' => __('Embed block'),
                                        default => __('Content block'),
                                    };
                                    $blockSettingsDescription = match ($activeBlockType) {
                                        'links' => __('Add links with labels, URLs, and supporting text.'),
                                        'social' => __('Add social profiles with icons and profile URLs.'),
                                        'contact' => __('Add contact methods such as phone, email, or address.'),
                                        'gallery' => __('Add image cards with captions and optional links.'),
                                        'faq' => __('Add questions and answers for visitors.'),
                                        'product' => __('Add product cards with image, price, and link.'),
                                        'header' => __('Add a text section with an optional button.'),
                                        'video' => __('Paste a YouTube or Vimeo URL and add an optional caption.'),
                                        'embed' => __('Add embed content or an external CTA.'),
                                        default => __('Configure this block.'),
                                    };
                                @endphp

                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $blockSettingsTitle }}</p>
                                    <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $blockSettingsDescription }}</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                    <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.title" :label="__('Block title')" placeholder="{{ __('Section title') }}" />
                                    <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.subtitle" :label="__('Block subtitle')" placeholder="{{ __('Short supporting text') }}" />
                                </div>

                                @if ($activeBlockType === 'video')
                                    <div class="space-y-5">
                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.button_url" :label="__('Video URL')" placeholder="https://www.youtube.com/watch?v=..." />
                                        <x-ui.emoji-textarea wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.content" :label="__('Caption')" trigger-position="inside-top-right" picker-align="right" rows="2" class="pt-2">{{ data_get($activeBlock, 'content') }}</x-ui.emoji-textarea>
                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.button_label" :label="__('Fallback button label')" placeholder="{{ __('Watch video') }}" />
                                    </div>
                                @elseif (in_array($activeBlockType, ['header', 'embed'], true))
                                    <x-ui.emoji-textarea wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.content" :label="$activeBlockType === 'embed' ? __('Embed code or note') : __('Content')" :placeholder="$activeBlockType === 'embed' ? __('Paste iframe, embed code, or short fallback text...') : __('Write section content...')" trigger-position="inside-top-right" picker-align="right" rows="3" class="pt-2">{{ data_get($activeBlock, 'content') }}</x-ui.emoji-textarea>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.button_label" :label="$activeBlockType === 'embed' ? __('Fallback button label') : __('Button label')" :placeholder="$activeBlockType === 'embed' ? __('Open') : ''" />
                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.button_url" :label="$activeBlockType === 'embed' ? __('Fallback URL') : __('Button URL')" :placeholder="$activeBlockType === 'embed' ? 'https://example.com' : ''" />
                                    </div>
                                @endif

                                @if (in_array($activeBlockType, ['links', 'social', 'contact', 'gallery', 'faq', 'product'], true))
                                    <div class="flex items-center justify-between gap-3 pt-1">
                                        <div>
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $itemsLabel }}</p>
                                        </div>
                                        <x-ui.button type="button" variant="outline" size="sm" wire:click="addBlockItem({{ $activeBlockIndex }})" class="shrink-0">
                                            <i class="fa-light fa-plus"></i>
                                            {{ $addItemLabel }}
                                        </x-ui.button>
                                    </div>

                                    <div class="space-y-3 pt-1">
                                        @foreach (data_get($activeBlock, 'items', []) as $itemIndex => $item)
                                            @php
                                                $itemAnalytics = data_get($blockAnalytics, 'blocks.'.$activeBlockIndex.'.items.'.$itemIndex, ['clicks' => 0, 'ctr' => 0]);
                                            @endphp
                                            <div wire:key="link-bio-block-item-{{ $activeBlockIndex }}-{{ $itemIndex }}-{{ $activeBlockType }}" class="rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-soft);">
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <div>
                                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Item') }} #{{ $itemIndex + 1 }}</p>
                                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">
                                                            {{ $itemsLabel }}
                                                            @if ((int) data_get($itemAnalytics, 'clicks', 0) > 0)
                                                                · {{ data_get($itemAnalytics, 'clicks') }} {{ __('clicks') }} · {{ data_get($itemAnalytics, 'ctr') }}% {{ __('CTR') }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <x-ui.button type="button" variant="danger" size="sm" wire:click="removeBlockItem({{ $activeBlockIndex }}, {{ $itemIndex }})" class="h-8 w-8 px-0">
                                                        <i class="fa-light fa-trash-can"></i>
                                                    </x-ui.button>
                                                </div>

                                                <div class="grid gap-3">
                                                    @if (in_array($activeBlockType, ['links', 'social', 'contact'], true))
                                                        @php
                                                            $currentIcon = (string) data_get($item, 'icon', 'fa-solid fa-link');
                                                            $iconPresets = match ($activeBlockType) {
                                                                'social' => [
                                                                    ['label' => __('Instagram'), 'icon' => 'fa-brands fa-instagram'],
                                                                    ['label' => __('Facebook'), 'icon' => 'fa-brands fa-facebook-f'],
                                                                    ['label' => __('TikTok'), 'icon' => 'fa-brands fa-tiktok'],
                                                                    ['label' => __('YouTube'), 'icon' => 'fa-brands fa-youtube'],
                                                                    ['label' => __('Website'), 'icon' => 'fa-solid fa-link'],
                                                                    ['label' => __('Hashtag'), 'icon' => 'fa-solid fa-hashtag'],
                                                                ],
                                                                'contact' => [
                                                                    ['label' => __('Phone'), 'icon' => 'fa-solid fa-phone'],
                                                                    ['label' => __('Email'), 'icon' => 'fa-solid fa-envelope'],
                                                                    ['label' => __('Location'), 'icon' => 'fa-solid fa-location-dot'],
                                                                    ['label' => __('Website'), 'icon' => 'fa-solid fa-link'],
                                                                ],
                                                                default => [
                                                                    ['label' => __('Link'), 'icon' => 'fa-solid fa-link'],
                                                                    ['label' => __('Website'), 'icon' => 'fa-solid fa-globe'],
                                                                    ['label' => __('File'), 'icon' => 'fa-solid fa-file-lines'],
                                                                    ['label' => __('Shop'), 'icon' => 'fa-solid fa-bag-shopping'],
                                                                    ['label' => __('Star'), 'icon' => 'fa-solid fa-star'],
                                                                    ['label' => __('Hashtag'), 'icon' => 'fa-solid fa-hashtag'],
                                                                ],
                                                            };
                                                        @endphp

                                                        <div>
                                                            <p class="mb-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Icon') }}</p>
                                                            <div class="grid grid-cols-3 gap-2">
                                                                @foreach ($iconPresets as $iconPreset)
                                                                    @php($isSelectedIcon = $currentIcon === $iconPreset['icon'])
                                                                    <button
                                                                        type="button"
                                                                        wire:click="setBlockItemIcon({{ $activeBlockIndex }}, {{ $itemIndex }}, '{{ $iconPreset['icon'] }}')"
                                                                        class="flex min-w-0 items-center gap-2 rounded-[0.85rem] border px-2.5 py-2 text-left text-xs font-semibold transition hover:-translate-y-0.5"
                                                                        style="{{ $isSelectedIcon ? 'border-color: rgba(var(--theme-accent-rgb),0.40); background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);' }}"
                                                                    >
                                                                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full" style="{{ $isSelectedIcon ? 'background-color: var(--theme-accent); color: #fff;' : 'background-color: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);' }}">
                                                                            <i class="{{ $iconPreset['icon'] }}"></i>
                                                                        </span>
                                                                        <span class="truncate">{{ $iconPreset['label'] }}</span>
                                                                    </button>
                                                                @endforeach
                                                            </div>

                                                            <details class="mt-2 rounded-[0.85rem] border px-3 py-2" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                                                                <summary class="cursor-pointer text-xs font-semibold" style="color: var(--theme-muted-text-color);">{{ __('Advanced icon picker') }}</summary>
                                                                <div class="mt-3">
                                                                    <x-ui.icon-picker wire:model.live="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.icon" :label="__('Font Awesome icon')" :preview-color="$accentColor" />
                                                                </div>
                                                            </details>
                                                        </div>
                                                    @endif

                                                    @if ($activeBlockType === 'faq')
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.label" :label="__('Question')" />
                                                        <x-ui.emoji-textarea wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.answer" :label="__('Answer')" trigger-position="inside-top-right" picker-align="right" rows="3" class="pt-2">{{ data_get($item, 'answer') }}</x-ui.emoji-textarea>
                                                    @elseif ($activeBlockType === 'gallery')
                                                        <x-ui.image-picker :name="'block_item_image_'.$activeBlockIndex.'_'.$itemIndex" context="portal" value-field="url" :value="(string) data_get($item, 'image', '')" :preview="(string) data_get($item, 'image', '')" :label="__('Image')" :button-label="__('Choose image')" :dialog-title="__('Choose gallery image')" :dialog-description="__('Select an image from Files.')" />
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.label" :label="__('Caption')" />
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.note" :label="__('Description')" />
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.url" :label="__('Optional link')" />
                                                    @elseif ($activeBlockType === 'product')
                                                        <x-ui.image-picker :name="'block_item_image_'.$activeBlockIndex.'_'.$itemIndex" context="portal" value-field="url" :value="(string) data_get($item, 'image', '')" :preview="(string) data_get($item, 'image', '')" :label="__('Product image')" :button-label="__('Choose image')" :dialog-title="__('Choose product image')" :dialog-description="__('Select an image from Files.')" />
                                                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.label" :label="__('Product name')" />
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.price" :label="__('Price')" />
                                                        </div>
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.note" :label="__('Description')" />
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.url" :label="__('Product link')" />
                                                    @elseif ($activeBlockType === 'contact')
                                                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.label" :label="__('Label')" />
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.value" :label="__('Contact value')" />
                                                        </div>
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.url" :label="__('Optional link')" />
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.note" :label="__('Note')" />
                                                    @else
                                                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.label" :label="$activeBlockType === 'social' ? __('Profile name') : __('Label')" />
                                                            <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.url" :label="$activeBlockType === 'social' ? __('Profile URL') : __('URL')" />
                                                        </div>
                                                        <x-ui.input wire:model.live.debounce.300ms="blocks.{{ $activeBlockIndex }}.items.{{ $itemIndex }}.note" :label="__('Supporting text')" />
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </x-ui.surface-card>
                        @endif
                    </div>

                    <div x-show="activeSidebarTab === 'templates'" x-transition.opacity class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Templates') }}</p>
                                <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Choose the base visual direction.') }}</p>
                            </div>
                            <x-ui.button href="{{ $templatesUrl }}" variant="outline" size="sm">
                                <i class="fa-light fa-grid-2"></i>
                                {{ __('Browse') }}
                            </x-ui.button>
                        </div>

                        <x-ui.surface-card padding="sm" class="space-y-4">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Background') }}</p>
                                <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Use a full-page background image for this template.') }}</p>
                            </div>

                            <x-ui.image-picker name="background_url" context="portal" value-field="url" :value="$backgroundUrl" :preview="$backgroundUrl" :label="__('Background image')" :error="$errors->first('backgroundUrl')" :button-label="__('Choose background')" :dialog-title="__('Choose background image')" :dialog-description="__('Select an image from Files.')" />

                            <div class="grid gap-x-3 gap-y-5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <x-ui.input type="number" min="0" max="85" wire:model.live.debounce.300ms="backgroundOverlay" :label="__('Overlay')" :error="$errors->first('backgroundOverlay')" />
                                <x-ui.select wire:model.live="backgroundPosition" :label="__('Position')">
                                    <option value="top">{{ __('Top') }}</option>
                                    <option value="center">{{ __('Center') }}</option>
                                    <option value="bottom">{{ __('Bottom') }}</option>
                                </x-ui.select>
                            </div>

                            <div class="pt-1">
                                <x-ui.select wire:model.live="backgroundFit" :label="__('Fit')">
                                    <option value="cover">{{ __('Cover') }}</option>
                                    <option value="contain">{{ __('Contain') }}</option>
                                    <option value="pattern">{{ __('Pattern') }}</option>
                                </x-ui.select>
                            </div>

                        </x-ui.surface-card>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($templateOptions as $templateOption)
                                <button wire:click="setTemplate('{{ $templateOption['key'] }}')" type="button" class="overflow-hidden rounded-[1rem] border text-left transition hover:-translate-y-0.5" style="{{ $templateKey === $templateOption['key'] ? 'border-color: rgba(var(--theme-accent-rgb), 0.34); background-color: rgba(var(--theme-accent-rgb), 0.08);' : 'border-color: var(--theme-border-color); background-color: var(--theme-surface-base);' }}">
                                    <div class="relative h-20 overflow-hidden" style="background: {{ data_get($templateOption, 'theme.page_bg', '#f8fafc') }};">
                                        <div class="h-8" style="background: {{ $templateOption['preview'] }};"></div>
                                        <div class="px-3 py-2">
                                            <span class="block h-2 w-14 rounded-full" style="background-color: {{ data_get($templateOption, 'theme.text', '#0f172a') }}; opacity: .88;"></span>
                                            <span class="mt-1 block h-1.5 w-20 rounded-full" style="background-color: {{ data_get($templateOption, 'theme.muted', '#64748b') }}; opacity: .7;"></span>
                                            <span class="mt-2 block h-3 rounded-full" style="background-color: {{ data_get($templateOption, 'theme.button_bg', data_get($templateOption, 'theme.accent', '#2563eb')) }};"></span>
                                        </div>
                                        @if ($templateKey === $templateOption['key'])
                                            <span class="absolute right-2 top-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-emerald-600 shadow-sm"><i class="fa-light fa-check text-[10px]"></i></span>
                                        @endif
                                    </div>
                                    <div class="px-3 py-2">
                                        <p class="truncate text-xs font-semibold" style="color: var(--theme-header-text-color);">{{ $templateOption['label'] }}</p>
                                        <p class="mt-1 truncate text-[10px] uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ str((string) ($templateOption['category'] ?? 'featured'))->headline() }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="border-t p-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-soft);">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button type="button" wire:click="saveDraft" class="flex-1">
                            <i class="fa-light fa-floppy-disk"></i>
                            {{ __('Save draft') }}
                        </x-ui.button>
                        @if ($publicUrl)
                            <x-ui.button href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer" variant="outline">
                                <i class="fa-light fa-arrow-up-right-from-square"></i>
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        </aside>

        <section class="bio-preview min-w-0">
            <x-ui.card padding="none" class="overflow-hidden">
                <div class="border-b px-5 py-3" style="border-color: var(--theme-border-color);">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Preview') }}</p>
                            <h2 class="mt-0.5 text-base font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ __('Live public page') }}</h2>
                            <p class="mt-0.5 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Same renderer as the public page.') }}</p>
                        </div>
                    </div>
                </div>
                <div
                    class="relative max-h-[calc(100vh-12rem)] min-h-[620px] overflow-y-auto px-4 py-4 sm:px-5"
                    x-on:dragenter.prevent="previewDropActive = true"
                    x-on:dragover.prevent="previewDropActive = true; $event.dataTransfer.dropEffect = 'copy'"
                    x-on:dragleave.self="previewDropActive = false"
                    x-on:drop.prevent="dropBlock()"
                    style="background:
                    radial-gradient(circle at top left, color-mix(in srgb, {{ $accentColor ?: '#2563eb' }} 13%, transparent), transparent 34%),
                    linear-gradient(180deg, var(--theme-surface-soft) 0%, var(--theme-surface-base) 100%);"
                >
                    <div
                        x-cloak
                        x-show="previewDropActive"
                        x-transition.opacity
                        class="pointer-events-none absolute inset-4 z-20 flex items-center justify-center rounded-[1.2rem] border-2 border-dashed"
                        style="border-color: rgba(var(--theme-accent-rgb),0.48); background-color: rgba(var(--theme-accent-rgb),0.08);"
                    >
                        <div class="rounded-[1rem] border bg-white/95 px-4 py-3 text-center shadow-lg" style="border-color: rgba(var(--theme-accent-rgb),0.20); color: var(--theme-header-text-color);">
                            <p class="text-sm font-semibold">{{ __('Drop block here') }}</p>
                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('It will be added to this bio page.') }}</p>
                        </div>
                    </div>
                    <div class="mx-auto max-w-[520px]">
                        @include('applinkbio::partials.renderer', [
                            'preview' => true,
                            'editable' => true,
                            'theme' => $theme,
                            'title' => $title,
                            'headline' => $headline,
                            'description' => $description,
                            'brandingText' => $brandingText,
                            'accentColor' => $accentColor,
                            'avatarUrl' => $avatarUrl,
                            'coverUrl' => $coverUrl,
                            'backgroundUrl' => $backgroundUrl,
                            'backgroundOverlay' => $backgroundOverlay,
                            'backgroundPosition' => $backgroundPosition,
                            'backgroundFit' => $backgroundFit,
                            'avatarStyle' => $avatarStyle,
                            'buttonStyle' => $buttonStyle,
                            'contentAlign' => $contentAlign,
                            'blocks' => $blocks,
                            'templateKey' => $templateKey,
                        ])
                    </div>
                </div>
            </x-ui.card>
        </section>
    </div>
</div>
