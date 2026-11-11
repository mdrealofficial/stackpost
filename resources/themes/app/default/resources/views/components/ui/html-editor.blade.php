@props(['name','label'=>null,'value'=>'','error'=>null,'help'=>null,'required'=>false,'rows'=>12])

@php
    $basePath = rtrim((string) request()->getBaseUrl(), '/');
    $normalizeUrl = static function (?string $url) use ($basePath): ?string {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $query = parse_url($url, PHP_URL_QUERY);
            if ($path !== '') {
                $relative = $query ? $path.'?'.$query : $path;
                if ($basePath !== '' && str_starts_with($relative, '/') && ! str_starts_with($relative, $basePath.'/') && $relative !== $basePath) return $basePath.$relative;
                return $relative;
            }
        }
        if ($basePath !== '' && str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/') && $url !== $basePath) return $basePath.$url;
        return $url;
    };
    $pickerEndpoint = $normalizeUrl(route('admin-files.picker-images', [], false));
    $pickerUploadEndpoint = $normalizeUrl(route('admin-files.picker-upload', [], false));
    $pickerUploadFromUrlEndpoint = $normalizeUrl(route('admin-files.picker-upload-from-url', [], false));
    $emojiGroups = [
        ['title' => __('Faces'), 'items' => ['😀','😂','😊','😍','🥳','😎','😭','😡','👍','🙏','🔥','🎉']],
        ['title' => __('Nature'), 'items' => ['🌿','🌷','🌹','🌻','🌈','☀️','⚡','❄️','🐶','🐱','🦄','🐬']],
        ['title' => __('Food'), 'items' => ['🍎','🍊','🍉','🍓','🥑','☕','🍔','🍟','🍕','🍣','🍰','🍺']],
        ['title' => __('Symbols'), 'items' => ['❤️','✅','❌','⚠️','❓','⭐','🌟','✨','💯','💡','📌','🚀']],
    ];
@endphp

<div {{ $attributes->only('class')->class('space-y-2.5') }} x-data="htmlEditor(@js($name), @js((string) $value), @js($pickerEndpoint), @js($pickerUploadEndpoint), @js($pickerUploadFromUrlEndpoint), @js($emojiGroups))" x-init="init()" x-on:html-editor:set.window="if (($event.detail?.name || '') === @js($name)) { setExternalContent($event.detail?.content || '') }">
    @if ($label)
        <x-ui.label>{{ $label }}</x-ui.label>
    @endif

    <div x-show="!fullscreen" class="rounded-[var(--theme-input-radius,0.75rem)] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface);">
        <div class="relative border-b px-3 py-2" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-input-surface) 88%, var(--theme-body-bg));" x-on:click.outside="closeMenus()">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" title="{{ __('Undo (Ctrl+Z)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('undo')"><i class="fa-light fa-rotate-left"></i></button>
                <button type="button" title="{{ __('Redo (Ctrl+Y)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('redo')"><i class="fa-light fa-rotate-right"></i></button>
                <span class="mx-1 h-6 w-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent);"></span>
                <button type="button" title="{{ __('Bold (Ctrl+B)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('bold')"><i class="fa-light fa-bold"></i></button>
                <button type="button" title="{{ __('Italic (Ctrl+I)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('italic')"><i class="fa-light fa-italic"></i></button>
                <button type="button" title="{{ __('Underline (Ctrl+U)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('underline')"><i class="fa-light fa-underline"></i></button>
                <button type="button" title="{{ __('Strike (Alt+Shift+S)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('strikeThrough')"><i class="fa-light fa-strikethrough"></i></button>
                <label title="{{ __('Text color') }}" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:mousedown.prevent="saveSelection()" x-on:click.prevent="$event.currentTarget.querySelector('input[type=color]')?.click()">
                    <input type="color" class="sr-only" x-on:click.stop x-on:focus="restoreSelection()" x-on:input="applyTextColor($event.target.value)" x-on:change="applyTextColor($event.target.value)">
                    <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">A</span>
                </label>
                <label title="{{ __('Highlight color') }}" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:mousedown.prevent="saveSelection()" x-on:click.prevent="$event.currentTarget.querySelector('input[type=color]')?.click()">
                    <input type="color" class="sr-only" x-on:click.stop x-on:focus="restoreSelection()" x-on:input="applyHighlightColor($event.target.value)" x-on:change="applyHighlightColor($event.target.value)">
                    <i class="fa-light fa-highlighter"></i>
                </label>
                <div class="relative">
                    <button type="button" title="{{ __('Headings') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('heading')">
                        <i class="fa-light fa-heading"></i>
                        <span x-text="currentHeadingLabel()"></span>
                        <i class="fa-light fa-chevron-down text-[11px]"></i>
                    </button>
                    <div x-cloak x-show="headingMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="applyParagraph(); closeMenus()"><span>{{ __('Paragraph') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+0</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H1'); closeMenus()"><span>H1</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+1</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H2'); closeMenus()"><span>H2</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+2</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H3'); closeMenus()"><span>H3</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+3</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H4'); closeMenus()"><span>H4</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+4</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H5'); closeMenus()"><span>H5</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+5</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H6'); closeMenus()"><span>H6</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+6</span></button>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" title="{{ __('Lists') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('list')">
                        <i class="fa-light fa-list"></i>
                        <span>{{ __('Lists') }}</span>
                        <i class="fa-light fa-chevron-down text-[11px]"></i>
                    </button>
                    <div x-cloak x-show="listMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('insertUnorderedList'); closeMenus()"><span>{{ __('Bullet list') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+Shift+8</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('insertOrderedList'); closeMenus()"><span>{{ __('Numbered list') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+Shift+7</span></button>
                    </div>
                </div>
                <span class="mx-1 h-6 w-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent);"></span>
                <button type="button" title="{{ __('Align left (Alt+Shift+L)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyLeft')"><i class="fa-light fa-align-left"></i></button>
                <button type="button" title="{{ __('Align center (Alt+Shift+E)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyCenter')"><i class="fa-light fa-align-center"></i></button>
                <button type="button" title="{{ __('Align right (Alt+Shift+R)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyRight')"><i class="fa-light fa-align-right"></i></button>
                <button type="button" title="{{ __('Justify (Alt+Shift+J)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyFull')"><i class="fa-light fa-align-justify"></i></button>
                <button type="button" title="{{ __('Outdent (Alt+Shift+O)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('outdent')"><i class="fa-light fa-outdent"></i></button>
                <button type="button" title="{{ __('Indent (Alt+Shift+P)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('indent')"><i class="fa-light fa-indent"></i></button>
                <div class="relative">
                    <button type="button" title="{{ __('Insert tools') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('insert')">
                        <i class="fa-light fa-square-plus"></i>
                        <span>{{ __('Insert') }}</span>
                        <i class="fa-light fa-chevron-down text-[11px]"></i>
                    </button>
                    <div x-cloak x-show="insertMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-60 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="createLink(); closeMenus()"><span>{{ __('Link') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+K</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="openDialog('image'); closeMenus()"><span>{{ __('Image from files') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+I</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="openDialog('video'); closeMenus()"><span>{{ __('Video / YouTube') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+V</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="toggleEmojiPicker(); closeMenus()"><span>{{ __('Emoji') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+M</span></button>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" title="{{ __('More tools') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('more')">
                        <i class="fa-light fa-wand-magic-sparkles"></i>
                        <span>{{ __('More') }}</span>
                        <i class="fa-light fa-chevron-down text-[11px]"></i>
                    </button>
                    <div x-cloak x-show="moreMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-64 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertBlockquote(); closeMenus()"><span>{{ __('Blockquote') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Q</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertCodeBlock(); closeMenus()"><span>{{ __('Code block') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+C</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertHorizontalRule(); closeMenus()"><span>{{ __('Horizontal rule') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+H</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyLeft'); closeMenus()"><span>{{ __('Align left') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+L</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyCenter'); closeMenus()"><span>{{ __('Align center') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+E</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyRight'); closeMenus()"><span>{{ __('Align right') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+R</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('unlink'); closeMenus()"><span>{{ __('Remove link') }}</span></button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('removeFormat'); closeMenus()"><span>{{ __('Clear formatting') }}</span></button>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <button type="button" title="{{ __('Toggle fullscreen (F11)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="toggleFullscreen()">
                        <i class="fa-light" x-bind:class="fullscreen ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                    <button type="button" title="{{ __('Toggle HTML mode (Ctrl+/)') }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="toggleSource()" x-text="sourceMode ? 'Visual' : 'HTML'"></button>
                </div>
            </div>
        </div>

        <div class="relative">
            <div x-cloak x-show="emojiOpen" x-transition.opacity.scale.95 class="absolute left-3 top-3 z-20 w-[20rem] overflow-hidden rounded-[1rem] border shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                <div class="border-b px-4 py-3" style="border-color: var(--theme-border-color);">
                    <input x-model="emojiSearch" type="text" class="flex h-10 w-full border px-3 text-sm outline-none" style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Search emoji...') }}">
                </div>
                <div class="max-h-72 overflow-y-auto px-4 py-4 space-y-4">
                    <template x-for="group in emojiGroups" :key="group.title">
                        <div x-show="emojiSearch === '' || `${group.title} ${group.items.join(' ')}`.toLowerCase().includes(emojiSearch.toLowerCase())">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500" x-text="group.title"></p>
                            <div class="grid grid-cols-6 gap-2">
                                <template x-for="emoji in group.items" :key="group.title + emoji">
                                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-[0.85rem] border text-xl transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent);" x-on:click="insertEmoji(emoji)" x-text="emoji"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <template x-if="!sourceMode">
                <div x-ref="editor" x-on:input="syncFromEditor()" x-on:mouseup="saveSelection()" x-on:keyup="saveSelection()" x-on:focus="saveSelection()" x-on:keydown="handleKeydown($event)" contenteditable="true" class="html-editor-surface w-full overflow-y-auto px-4 py-3.5 text-sm leading-6 outline-none" x-bind:style="editorPaneStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: 56rem; color: var(--theme-input-text);"></div>
            </template>

            <template x-if="sourceMode">
                <div class="overflow-hidden" x-bind:style="sourcePaneStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: 56rem; background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 96%, transparent), color-mix(in srgb, var(--theme-surface-soft) 82%, var(--theme-surface-base)));">
                    <div class="flex items-center justify-between border-b px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent); color: var(--theme-muted-text-color);">
                        <span>{{ __('HTML source') }}</span>
                        <span x-text="`${codeLineCount()} {{ __('lines') }}`"></span>
                    </div>
                    <div class="grid grid-cols-[56px_minmax(0,1fr)] overflow-hidden" x-bind:style="sourceGridStyle()" style="max-height: calc(56rem - 2.5rem);">
                        <div x-ref="gutter" class="select-none overflow-hidden border-r px-3 py-3 text-right text-xs leading-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent); color: color-mix(in srgb, var(--theme-muted-text-color) 82%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 84%, transparent);">
                            <template x-for="line in codeLines()" :key="line"><div x-text="line"></div></template>
                        </div>
                        <textarea x-ref="textarea" x-model="content" x-on:input="syncFromTextarea()" x-on:scroll="syncCodeScroll()" x-on:keydown="handleKeydown($event)" spellcheck="false" wrap="soft" class="w-full overflow-y-auto border-0 bg-transparent px-4 py-3 font-mono text-[13px] leading-6 outline-none" x-bind:style="sourceTextareaStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: calc(56rem - 2.5rem); color: var(--theme-input-text); tab-size: 2; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; resize: vertical;" @if ($required) aria-required="true" @endif></textarea>
                    </div>
                </div>
            </template>
        </div>

        <textarea name="{{ $name }}" x-ref="hidden" x-model="content" class="hidden" @if ($required) aria-required="true" data-required="true" @endif></textarea>
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="fullscreen" class="fixed inset-0 z-[129] bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55"></div>
    </template>

    <template x-teleport="body">
        <div x-cloak x-show="fullscreen" class="fixed inset-4 z-[130] overflow-hidden rounded-[1rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface);">
            <div class="relative border-b px-3 py-2" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-input-surface) 88%, var(--theme-body-bg));" x-on:click.outside="closeMenus()">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" title="{{ __('Undo (Ctrl+Z)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('undo')"><i class="fa-light fa-rotate-left"></i></button>
                    <button type="button" title="{{ __('Redo (Ctrl+Y)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('redo')"><i class="fa-light fa-rotate-right"></i></button>
                    <span class="mx-1 h-6 w-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent);"></span>
                    <button type="button" title="{{ __('Bold (Ctrl+B)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('bold')"><i class="fa-light fa-bold"></i></button>
                    <button type="button" title="{{ __('Italic (Ctrl+I)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('italic')"><i class="fa-light fa-italic"></i></button>
                    <button type="button" title="{{ __('Underline (Ctrl+U)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('underline')"><i class="fa-light fa-underline"></i></button>
                    <button type="button" title="{{ __('Strike (Alt+Shift+S)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('strikeThrough')"><i class="fa-light fa-strikethrough"></i></button>
                    <label title="{{ __('Text color') }}" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:mousedown.prevent="saveSelection()" x-on:click.prevent="$event.currentTarget.querySelector('input[type=color]')?.click()">
                        <input type="color" class="sr-only" x-on:click.stop x-on:focus="restoreSelection()" x-on:input="applyTextColor($event.target.value)" x-on:change="applyTextColor($event.target.value)">
                        <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">A</span>
                    </label>
                    <label title="{{ __('Highlight color') }}" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:mousedown.prevent="saveSelection()" x-on:click.prevent="$event.currentTarget.querySelector('input[type=color]')?.click()">
                        <input type="color" class="sr-only" x-on:click.stop x-on:focus="restoreSelection()" x-on:input="applyHighlightColor($event.target.value)" x-on:change="applyHighlightColor($event.target.value)">
                        <i class="fa-light fa-highlighter"></i>
                    </label>
                    <div class="relative">
                        <button type="button" title="{{ __('Headings') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('heading')">
                            <i class="fa-light fa-heading"></i>
                            <span x-text="currentHeadingLabel()"></span>
                            <i class="fa-light fa-chevron-down text-[11px]"></i>
                        </button>
                        <div x-cloak x-show="headingMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="applyParagraph(); closeMenus()"><span>{{ __('Paragraph') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+0</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H1'); closeMenus()"><span>H1</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+1</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H2'); closeMenus()"><span>H2</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+2</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H3'); closeMenus()"><span>H3</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+3</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H4'); closeMenus()"><span>H4</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+4</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H5'); closeMenus()"><span>H5</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+5</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="heading('H6'); closeMenus()"><span>H6</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+6</span></button>
                        </div>
                    </div>
                    <div class="relative">
                        <button type="button" title="{{ __('Lists') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('list')">
                            <i class="fa-light fa-list"></i>
                            <span>{{ __('Lists') }}</span>
                            <i class="fa-light fa-chevron-down text-[11px]"></i>
                        </button>
                        <div x-cloak x-show="listMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('insertUnorderedList'); closeMenus()"><span>{{ __('Bullet list') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+Shift+8</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('insertOrderedList'); closeMenus()"><span>{{ __('Numbered list') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+Shift+7</span></button>
                        </div>
                    </div>
                    <span class="mx-1 h-6 w-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent);"></span>
                    <button type="button" title="{{ __('Align left (Alt+Shift+L)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyLeft')"><i class="fa-light fa-align-left"></i></button>
                    <button type="button" title="{{ __('Align center (Alt+Shift+E)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyCenter')"><i class="fa-light fa-align-center"></i></button>
                    <button type="button" title="{{ __('Align right (Alt+Shift+R)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyRight')"><i class="fa-light fa-align-right"></i></button>
                    <button type="button" title="{{ __('Justify (Alt+Shift+J)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('justifyFull')"><i class="fa-light fa-align-justify"></i></button>
                    <button type="button" title="{{ __('Outdent (Alt+Shift+O)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('outdent')"><i class="fa-light fa-outdent"></i></button>
                    <button type="button" title="{{ __('Indent (Alt+Shift+P)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="format('indent')"><i class="fa-light fa-indent"></i></button>
                    <div class="relative">
                        <button type="button" title="{{ __('Insert tools') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('insert')">
                            <i class="fa-light fa-square-plus"></i>
                            <span>{{ __('Insert') }}</span>
                            <i class="fa-light fa-chevron-down text-[11px]"></i>
                        </button>
                        <div x-cloak x-show="insertMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-60 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="createLink(); closeMenus()"><span>{{ __('Link') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Ctrl+K</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="openDialog('image'); closeMenus()"><span>{{ __('Image from files') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+I</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="openDialog('video'); closeMenus()"><span>{{ __('Video / YouTube') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+V</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="toggleEmojiPicker(); closeMenus()"><span>{{ __('Emoji') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+M</span></button>
                        </div>
                    </div>
                    <div class="relative">
                        <button type="button" title="{{ __('More tools') }}" class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click.stop="toggleMenu('more')">
                            <i class="fa-light fa-wand-magic-sparkles"></i>
                            <span>{{ __('More') }}</span>
                            <i class="fa-light fa-chevron-down text-[11px]"></i>
                        </button>
                        <div x-cloak x-show="moreMenuOpen" x-transition.opacity.scale.95 class="absolute left-0 top-full z-30 mt-2 w-64 overflow-hidden rounded-[1rem] border py-2 shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertBlockquote(); closeMenus()"><span>{{ __('Blockquote') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Q</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertCodeBlock(); closeMenus()"><span>{{ __('Code block') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+C</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="insertHorizontalRule(); closeMenus()"><span>{{ __('Horizontal rule') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+H</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyLeft'); closeMenus()"><span>{{ __('Align left') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+L</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyCenter'); closeMenus()"><span>{{ __('Align center') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+E</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('justifyRight'); closeMenus()"><span>{{ __('Align right') }}</span><span class="text-[11px]" style="color: var(--theme-muted-text-color);">Alt+Shift+R</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('unlink'); closeMenus()"><span>{{ __('Remove link') }}</span></button>
                            <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="color: var(--theme-header-text-color);" x-on:click="format('removeFormat'); closeMenus()"><span>{{ __('Clear formatting') }}</span></button>
                        </div>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <button type="button" title="{{ __('Toggle fullscreen (F11)') }}" class="rounded-lg px-2.5 py-1.5 text-sm transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="toggleFullscreen()">
                            <i class="fa-light" x-bind:class="fullscreen ? 'fa-compress' : 'fa-expand'"></i>
                        </button>
                        <button type="button" title="{{ __('Toggle HTML mode (Ctrl+/)') }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] transition hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="toggleSource()" x-text="sourceMode ? 'Visual' : 'HTML'"></button>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div x-cloak x-show="emojiOpen" x-transition.opacity.scale.95 class="absolute left-3 top-3 z-20 w-[20rem] overflow-hidden rounded-[1rem] border shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                    <div class="border-b px-4 py-3" style="border-color: var(--theme-border-color);">
                        <input x-model="emojiSearch" type="text" class="flex h-10 w-full border px-3 text-sm outline-none" style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Search emoji...') }}">
                    </div>
                    <div class="max-h-72 overflow-y-auto px-4 py-4 space-y-4">
                        <template x-for="group in emojiGroups" :key="group.title">
                            <div x-show="emojiSearch === '' || `${group.title} ${group.items.join(' ')}`.toLowerCase().includes(emojiSearch.toLowerCase())">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500" x-text="group.title"></p>
                                <div class="grid grid-cols-6 gap-2">
                                    <template x-for="emoji in group.items" :key="group.title + emoji">
                                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-[0.85rem] border text-xl transition hover:bg-slate-50 dark:hover:bg-slate-800/70" style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent);" x-on:click="insertEmoji(emoji)" x-text="emoji"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="!sourceMode">
                    <div x-ref="fullscreenEditor" x-on:input="syncFromFullscreenEditor()" x-on:mouseup="saveSelection()" x-on:keyup="saveSelection()" x-on:focus="saveSelection()" x-on:keydown="handleKeydown($event)" contenteditable="true" class="html-editor-surface w-full overflow-y-auto px-4 py-3.5 text-sm leading-6 outline-none" x-bind:style="editorPaneStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: 56rem; color: var(--theme-input-text);"></div>
                </template>

                <template x-if="sourceMode">
                    <div class="overflow-hidden" x-bind:style="sourcePaneStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: 56rem; background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 96%, transparent), color-mix(in srgb, var(--theme-surface-soft) 82%, var(--theme-surface-base)));">
                        <div class="flex items-center justify-between border-b px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent); color: var(--theme-muted-text-color);">
                            <span>{{ __('HTML source') }}</span>
                            <span x-text="`${codeLineCount()} {{ __('lines') }}`"></span>
                        </div>
                        <div class="grid grid-cols-[56px_minmax(0,1fr)] overflow-hidden" x-bind:style="sourceGridStyle()" style="max-height: calc(56rem - 2.5rem);">
                            <div x-ref="fullscreenGutter" class="select-none overflow-hidden border-r px-3 py-3 text-right text-xs leading-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 74%, transparent); color: color-mix(in srgb, var(--theme-muted-text-color) 82%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 84%, transparent);">
                                <template x-for="line in codeLines()" :key="line"><div x-text="line"></div></template>
                            </div>
                            <textarea x-ref="fullscreenTextarea" x-model="content" x-on:input="syncFromTextarea()" x-on:scroll="syncCodeScroll()" x-on:keydown="handleKeydown($event)" spellcheck="false" wrap="soft" class="w-full overflow-y-auto border-0 bg-transparent px-4 py-3 font-mono text-[13px] leading-6 outline-none" x-bind:style="sourceTextareaStyle()" style="min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: calc(56rem - 2.5rem); color: var(--theme-input-text); tab-size: 2; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; resize: vertical;" @if ($required) required @endif></textarea>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-cloak x-show="dialogOpen" class="fixed inset-0 z-[140] flex items-center justify-center p-4 sm:p-6" x-on:keydown.escape.window="closeDialog()">
            <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeDialog()"></div>
            <div x-show="dialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-4xl">
                <div class="overflow-hidden rounded-[1.2rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                    <div class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6 sm:py-5">
                        <div class="min-w-0">
                            <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white" x-text="dialogType === 'image' ? @js(__('Insert image')) : @js(__('Insert video'))"></h3>
                            <p class="mt-1 text-[15px] leading-7 text-slate-500 dark:text-slate-400" x-text="dialogType === 'image' ? @js(__('Choose an image from your files.')) : @js(__('Insert a YouTube video or pick an MP4 from your files.'))"></p>
                        </div>
                        <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeDialog()"><i class="fa-light fa-xmark text-lg"></i></button>
                    </div>
                    <div class="max-h-[75vh] overflow-y-auto border-t px-5 py-5 sm:px-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        <div class="space-y-4">
                            <template x-if="dialogType === 'video'">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-bind:style="videoTab==='files' ? 'border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08);' : ''" x-on:click="videoTab='files'; search=''; youtubeUrl=''; loadFiles(true)">{{ __('MP4 from files') }}</button>
                                    <button type="button" class="rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-bind:style="videoTab==='youtube' ? 'border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08);' : ''" x-on:click="videoTab='youtube'; search=''; files=[]; youtubeUrl='';">{{ __('YouTube') }}</button>
                                </div>
                            </template>

                            <div class="grid gap-3 lg:grid-cols-2">
                                <x-ui.input
                                    x-show="dialogType === 'image'"
                                    x-model="mediaAlt"
                                    :label="__('Alt text')"
                                    :placeholder="__('Describe the image for accessibility...')"
                                />
                                <x-ui.input
                                    x-model="mediaCaption"
                                    :label="__('Caption / note')"
                                    :placeholder="__('Optional caption shown below the media...')"
                                />
                            </div>

                            <template x-if="dialogType === 'image' || videoTab === 'files'">
                                <div class="space-y-4">
                                    <input x-ref="mediaUploadInput" type="file" class="hidden" multiple x-bind:accept="dialogType === 'video' ? 'video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov' : 'image/*'" x-on:change="uploadSelectedFiles($event)">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-on:click="$refs.mediaUploadInput.click()">
                                            <i class="fa-light fa-upload"></i>
                                            <span x-text="dialogType === 'video' ? @js(__('Upload video')) : @js(__('Upload image'))"></span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-on:click="showRemoteUrlForm = !showRemoteUrlForm; remoteUrl = ''">
                                            <i class="fa-light fa-link-simple"></i>
                                            <span x-text="dialogType === 'video' ? @js(__('Import video URL')) : @js(__('Import image URL'))"></span>
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-on:click="loadFiles(true)">
                                            <i class="fa-light fa-rotate-right"></i>
                                            <span>{{ __('Refresh') }}</span>
                                        </button>
                                    </div>
                                    <div x-cloak x-show="showRemoteUrlForm" x-transition.opacity.scale.95 class="rounded-[1rem] border p-4 space-y-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
                                        <x-ui.input x-model="remoteUrl" :label="__('Remote URL')" :placeholder="__('https://example.com/file.jpg')" />
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button type="button" variant="outline" x-on:click="showRemoteUrlForm = false; remoteUrl = ''">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" x-on:click="importFromUrl()" x-bind:disabled="!remoteUrl.trim() || importingUrl">
                                                <span x-text="importingUrl ? @js(__('Importing...')) : @js(__('Import now'))"></span>
                                            </x-ui.button>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px]">
                                        <x-ui.input x-model="search" :label="__('Search')" :placeholder="__('Search files...')" />
                                        <x-ui.select x-model="sort" :label="__('Sort')">
                                            <option value="newest">{{ __('Newest first') }}</option>
                                            <option value="oldest">{{ __('Oldest first') }}</option>
                                            <option value="name_asc">{{ __('Name A-Z') }}</option>
                                            <option value="name_desc">{{ __('Name Z-A') }}</option>
                                        </x-ui.select>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-on:click="goToRoot()">
                                            <i class="fa-light fa-house"></i>
                                            <span>{{ __('Root') }}</span>
                                        </button>
                                        <template x-for="crumb in breadcrumbs" :key="crumb.idSecure">
                                            <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);" x-on:click="openFolder(crumb)">
                                                <i class="fa-light fa-folder"></i>
                                                <span x-text="crumb.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="max-h-[28rem] overflow-y-auto pr-1" x-on:scroll.passive="handleScroll($event.target)">
                                        <template x-if="folders.length">
                                            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                <template x-for="folder in folders" :key="`folder-${folder.idSecure}`">
                                                    <button type="button" class="flex items-center gap-3 rounded-[1rem] border px-4 py-3 text-left transition hover:-translate-y-0.5" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);" x-on:click="openFolder(folder)">
                                                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-[0.9rem]" style="background-color: color-mix(in srgb, rgba(var(--theme-accent-rgb), 0.12) 92%, transparent); color: rgba(var(--theme-accent-rgb), 1);">
                                                            <i class="fa-light fa-folder text-lg"></i>
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="folder.name"></span>
                                                            <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);" x-text="folder.note || '{{ __('Open folder') }}'"></span>
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="files.length">
                                            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                                <template x-for="file in files" :key="`${dialogType}-${file.id}`">
                                                    <button type="button" class="overflow-hidden rounded-[1rem] border text-left transition hover:-translate-y-0.5" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);" x-on:click="dialogType === 'image' ? insertImage(file) : insertVideo(file)">
                                                        <div class="aspect-square overflow-hidden" style="background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent);">
                                                            <template x-if="dialogType === 'image'"><img x-bind:src="file.previewUrl || file.embedUrl || file.url" x-bind:alt="file.name" class="h-full w-full object-cover" x-on:error="$el.src = file.url || ''"></template>
                                                            <template x-if="dialogType === 'video'"><video x-bind:src="file.previewUrl || file.embedUrl || file.url" class="h-full w-full object-cover" muted preload="metadata"></video></template>
                                                        </div>
                                                        <div class="space-y-1 px-3 py-3">
                                                            <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="file.name"></p>
                                                            <p class="text-xs" style="color: var(--theme-muted-text-color);" x-text="file.size"></p>
                                                        </div>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!files.length && !loading"><x-ui.empty icon="fa-light fa-photo-film" :title="__('No files found.')" :description="__('Use upload actions above or add media in your file library first, then return here to insert it.')" /></template>
                                        <template x-if="loading">
                                            <div class="flex items-center justify-center py-8">
                                                <div class="inline-flex items-center gap-3 text-sm" style="color: var(--theme-muted-text-color);">
                                                    <i class="fa-light fa-loader animate-spin"></i>
                                                    <span>{{ __('Loading files...') }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="dialogType === 'video' && videoTab === 'youtube'">
                                <div class="space-y-4">
                                    <x-ui.input x-model="youtubeUrl" :label="__('YouTube URL')" :placeholder="__('https://www.youtube.com/watch?v=...')" />
                                    <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
                                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Paste a YouTube watch, short, embed, or youtu.be URL. The editor will convert it to an embedded player.') }}</p>
                                        <div class="mt-4 flex justify-end">
                                            <x-ui.button type="button" x-on:click="insertYoutube()" x-bind:disabled="!youtubeEmbedUrl(youtubeUrl)">
                                                {{ __('Insert YouTube video') }}
                                            </x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @if ($error)
        <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
    @elseif ($help)
        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $help }}</p>
    @endif
</div>

@once
<style>
.html-editor-surface > :first-child { margin-top: 0; }
.html-editor-surface > :last-child { margin-bottom: 0; }
.html-editor-surface p { margin: 0 0 1rem; line-height: 1.85; }
.html-editor-surface h1,
.html-editor-surface h2,
.html-editor-surface h3,
.html-editor-surface h4,
.html-editor-surface h5,
.html-editor-surface h6 {
    margin: 1.4em 0 0.7em;
    color: var(--theme-header-text-color);
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.2;
}
.html-editor-surface h1 { font-size: 2rem; }
.html-editor-surface h2 { font-size: 1.6rem; }
.html-editor-surface h3 { font-size: 1.35rem; }
.html-editor-surface h4 { font-size: 1.15rem; }
.html-editor-surface h5 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.04em; }
.html-editor-surface h6 { font-size: 0.92rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--theme-muted-text-color); }
.html-editor-surface ul,
.html-editor-surface ol {
    margin: 0 0 1rem 1.35rem;
    padding-left: 0.75rem;
}
.html-editor-surface ul { list-style: disc outside; }
.html-editor-surface ol { list-style: decimal outside; }
.html-editor-surface ul ul { list-style-type: circle; }
.html-editor-surface ul ul ul { list-style-type: square; }
.html-editor-surface ol ol { list-style-type: lower-alpha; }
.html-editor-surface ol ol ol { list-style-type: lower-roman; }
.html-editor-surface ul[style*="text-align: center"],
.html-editor-surface ol[style*="text-align: center"] {
    margin-left: 0;
    padding-left: 0;
    list-style-position: inside;
    text-align: center;
}
.html-editor-surface ul[style*="text-align: right"],
.html-editor-surface ol[style*="text-align: right"] {
    margin-left: 0;
    padding-left: 0;
    list-style-position: inside;
    text-align: right;
}
.html-editor-surface ul[style*="text-align: justify"],
.html-editor-surface ol[style*="text-align: justify"] {
    text-align: justify;
}
.html-editor-surface li {
    display: list-item;
    margin: 0.35rem 0;
}
.html-editor-surface blockquote {
    margin: 1.25rem 0;
    padding: 1rem 1.15rem;
    border-left: 4px solid rgba(var(--theme-accent-rgb), 0.65);
    border-radius: 0 1rem 1rem 0;
    background: color-mix(in srgb, rgba(var(--theme-accent-rgb), 0.08) 65%, var(--theme-surface-soft));
    color: var(--theme-header-text-color);
    font-size: 1.02em;
}
.html-editor-surface blockquote p:last-child { margin-bottom: 0; }
.html-editor-surface pre {
    margin: 1.25rem 0;
    padding: 1rem 1.1rem;
    overflow-x: auto;
    border: 1px solid color-mix(in srgb, var(--theme-border-color) 78%, transparent);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--theme-surface-soft) 78%, var(--theme-body-bg));
    color: var(--theme-input-text);
}
.html-editor-surface pre code {
    padding: 0;
    border-radius: 0;
    background: transparent;
    font-size: 0.92rem;
}
.html-editor-surface code {
    padding: 0.12rem 0.38rem;
    border-radius: 0.45rem;
    background: color-mix(in srgb, var(--theme-surface-soft) 78%, var(--theme-body-bg));
    font-size: 0.92em;
}
.html-editor-surface hr {
    margin: 1.5rem 0;
    border: 0;
    border-top: 1px solid color-mix(in srgb, var(--theme-border-color) 82%, transparent);
}
.html-editor-surface a {
    color: rgba(var(--theme-accent-rgb), 1);
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 0.18em;
}
.html-editor-surface img,
.html-editor-surface video,
.html-editor-surface iframe {
    display: block;
    max-width: 100%;
}
.html-editor-surface figure {
    width: min(100%, 590px);
    margin: 1.25rem auto;
    overflow: hidden;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--theme-surface-soft) 78%, var(--theme-body-bg));
    box-shadow: 0 14px 34px -28px rgba(15, 23, 42, 0.28);
}
.html-editor-surface img {
    width: auto;
    max-width: min(100%, 590px);
    margin: 0;
    border-radius: 0;
}
.html-editor-surface figcaption {
    padding: 0.85rem 1rem 0.95rem;
    color: var(--theme-muted-text-color);
    font-size: 0.92rem;
    line-height: 1.7;
    text-align: left;
    background: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);
    border-top: 1px solid color-mix(in srgb, var(--theme-border-color) 78%, transparent);
}
.html-editor-surface .embedded-video {
    margin: 0;
    width: min(100%, 590px);
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 0;
    background: color-mix(in srgb, var(--theme-surface-soft) 78%, var(--theme-body-bg));
}
.html-editor-surface .embedded-video video,
.html-editor-surface .embedded-video iframe {
    width: 100%;
    height: 100%;
}
.html-editor-surface .embedded-video video {
    object-fit: contain;
    background: #000;
}
</style>
<script>
window.htmlEditor = window.htmlEditor || function (editorName, initialContent, endpoint, uploadEndpoint, uploadFromUrlEndpoint, emojiGroups) {
    return {
        editorName: editorName || '',
        content: initialContent || '',
        sourceMode: false,
        savedSelection: null,
        dialogOpen: false,
        dialogType: 'image',
        videoTab: 'files',
        endpoint: endpoint || '',
        uploadEndpoint: uploadEndpoint || '',
        uploadFromUrlEndpoint: uploadFromUrlEndpoint || '',
        files: [],
        folders: [],
        breadcrumbs: [],
        currentFolder: null,
        loading: false,
        uploadingFiles: false,
        importingUrl: false,
        page: 1,
        hasMore: true,
        search: '',
        sort: 'newest',
        searchTimer: null,
        youtubeUrl: '',
        remoteUrl: '',
        mediaAlt: '',
        mediaCaption: '',
        showRemoteUrlForm: false,
        emojiOpen: false,
        emojiSearch: '',
        emojiGroups: Array.isArray(emojiGroups) ? emojiGroups : [],
        headingMenuOpen: false,
        listMenuOpen: false,
        insertMenuOpen: false,
        moreMenuOpen: false,
        fullscreen: false,
        currentBlock: '{{ __('Paragraph') }}',
        csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
        getActiveEditor() {
            const refs = this.$refs || {};
            return this.fullscreen ? (refs.fullscreenEditor || refs.editor || null) : (refs.editor || refs.fullscreenEditor || null);
        },
        setEditorHtml(target, html) {
            if (!(target instanceof HTMLElement)) return;
            if (target.innerHTML !== html) target.innerHTML = html;
        },
        syncEditorRefs() {
            const refs = this.$refs || {};
            this.setEditorHtml(refs.editor || null, this.content);
            this.setEditorHtml(refs.fullscreenEditor || null, this.content);
        },
        init() {
            this.$nextTick(() => {
                this.syncEditorRefs();
                this.updateCurrentBlock();
                this.notifyChange();
            });
            this.$watch('search', () => {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => {
                    if (this.dialogOpen && (this.dialogType === 'image' || this.videoTab === 'files')) this.loadFiles(true);
                }, 250);
            });
            this.$watch('sort', () => {
                if (this.dialogOpen && (this.dialogType === 'image' || this.videoTab === 'files')) this.loadFiles(true);
            });
        },
        notifyChange() {
            this.$dispatch('html-editor:change', {
                name: this.editorName,
                content: this.content,
            });
        },
        codeLines() { return Array.from({ length: Math.max(1, String(this.content || '').split('\n').length) }, (_, i) => i + 1) },
        codeLineCount() { return Math.max(1, String(this.content || '').split('\n').length) },
        containerStyle() {
            return this.fullscreen
                ? 'position: fixed; inset: 1rem; z-index: 130; border-color: var(--theme-border-color); background-color: var(--theme-input-surface); border-radius: 1rem; box-shadow: 0 32px 80px -34px rgba(15,23,42,0.32);'
                : 'border-color: var(--theme-border-color); background-color: var(--theme-input-surface);';
        },
        editorPaneStyle() {
            return `min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: ${this.fullscreen ? 'calc(100vh - 8rem)' : '56rem'}; color: var(--theme-input-text);`;
        },
        sourcePaneStyle() {
            return `min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: ${this.fullscreen ? 'calc(100vh - 8rem)' : '56rem'}; background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 96%, transparent), color-mix(in srgb, var(--theme-surface-soft) 82%, var(--theme-surface-base)));`;
        },
        sourceGridStyle() {
            return `max-height: ${this.fullscreen ? 'calc(100vh - 10.5rem)' : 'calc(56rem - 2.5rem)'};`;
        },
        sourceTextareaStyle() {
            return `min-height: {{ max(8, (int) $rows) * 1.5 }}rem; max-height: ${this.fullscreen ? 'calc(100vh - 10.5rem)' : 'calc(56rem - 2.5rem)'}; color: var(--theme-input-text); tab-size: 2; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; resize: vertical;`;
        },
        syncCodeScroll() {
            const gutter = this.fullscreen ? this.$refs.fullscreenGutter : this.$refs.gutter;
            const textarea = this.fullscreen ? this.$refs.fullscreenTextarea : this.$refs.textarea;
            if (gutter && textarea) gutter.scrollTop = textarea.scrollTop;
        },
        syncFromEditor() {
            const editor = this.$refs?.editor || null;
            if (!(editor instanceof HTMLElement)) return;
            this.content = editor.innerHTML;
            this.setEditorHtml(this.$refs?.fullscreenEditor || null, this.content);
            this.notifyChange();
        },
        syncFromFullscreenEditor() {
            const editor = this.$refs?.fullscreenEditor || null;
            if (!(editor instanceof HTMLElement)) return;
            this.content = editor.innerHTML;
            this.setEditorHtml(this.$refs?.editor || null, this.content);
            this.notifyChange();
        },
        syncFromTextarea() {
            const textarea = this.fullscreen ? this.$refs.fullscreenTextarea : this.$refs.textarea;
            this.content = textarea ? textarea.value : this.content;
            this.syncCodeScroll();
            this.notifyChange();
        },
        toggleMenu(menu) {
            this.headingMenuOpen = menu === 'heading' ? !this.headingMenuOpen : false;
            this.listMenuOpen = menu === 'list' ? !this.listMenuOpen : false;
            this.insertMenuOpen = menu === 'insert' ? !this.insertMenuOpen : false;
            this.moreMenuOpen = menu === 'more' ? !this.moreMenuOpen : false;
            this.saveSelection();
        },
        closeMenus() {
            this.headingMenuOpen = false;
            this.listMenuOpen = false;
            this.insertMenuOpen = false;
            this.moreMenuOpen = false;
        },
        saveSelection() {
            if (this.sourceMode) return;
            const editor = this.getActiveEditor();
            const s = window.getSelection();
            if (!s || s.rangeCount === 0 || !editor || !editor.contains(s.anchorNode)) return;
            this.savedSelection = s.getRangeAt(0).cloneRange();
            this.updateCurrentBlock();
        },
        restoreSelection() {
            if (this.sourceMode) return;
            const editor = this.getActiveEditor();
            if (!editor) return;
            editor.focus();
            const s = window.getSelection();
            if (!s) return;
            s.removeAllRanges();
            if (this.savedSelection) s.addRange(this.savedSelection);
        },
        format(command, value = null) {
            if (this.sourceMode) return;
            this.closeMenus();
            this.restoreSelection();
            document.execCommand(command, false, value);
            this.syncFromEditor();
            this.saveSelection();
        },
        applyTextColor(color) {
            if (!color) return;
            this.format('foreColor', color);
        },
        applyHighlightColor(color) {
            if (!color) return;
            this.closeMenus();
            this.restoreSelection();
            if (document.queryCommandSupported && document.queryCommandSupported('hiliteColor')) {
                document.execCommand('hiliteColor', false, color);
            } else {
                document.execCommand('backColor', false, color);
            }
            this.syncFromEditor();
            this.saveSelection();
        },
        heading(tag) { this.format('formatBlock', tag) },
        applyParagraph() { this.format('formatBlock', 'P') },
        updateCurrentBlock() {
            if (this.sourceMode) return this.currentBlock = 'HTML';
            const editor = this.getActiveEditor();
            if (!editor) return this.currentBlock = '{{ __('Paragraph') }}';
            const selection = window.getSelection();
            let node = selection && selection.anchorNode ? selection.anchorNode : null;
            if (node && node.nodeType === Node.TEXT_NODE) node = node.parentElement;
            while (node && node !== editor) {
                const tag = String(node.tagName || '').toUpperCase();
                if (/^H[1-6]$/.test(tag)) return this.currentBlock = tag;
                if (tag === 'P') return this.currentBlock = '{{ __('Paragraph') }}';
                node = node.parentElement;
            }
            this.currentBlock = '{{ __('Paragraph') }}';
        },
        currentHeadingLabel() {
            return this.currentBlock;
        },
        createLink() {
            const url = window.prompt('URL');
            if (!url) return;
            this.format('createLink', url);
        },
        insertBlockquote() { this.insertMarkup('<blockquote><p>Your quote here</p></blockquote><p><br></p>') },
        insertCodeBlock() { this.insertMarkup('<pre><code>// your code here</code></pre><p><br></p>') },
        insertHorizontalRule() { this.insertMarkup('<hr><p><br></p>') },
        prettyHtml(value) {
            const source = String(value || '').trim();
            if (source === '') return '';

            const normalized = source
                .replace(/>\s+</g, '><')
                .replace(/</g, '\n<')
                .replace(/\n{2,}/g, '\n')
                .trim();

            const lines = normalized.split('\n').filter(Boolean);
            const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
            let indent = 0;

            return lines.map((line) => {
                const trimmed = line.trim();
                const isClosing = /^<\//.test(trimmed);
                const tagMatch = trimmed.match(/^<\/?([a-zA-Z0-9-:]+)/);
                const tagName = tagMatch ? tagMatch[1].toLowerCase() : null;
                const selfClosing = /\/>$/.test(trimmed) || (tagName ? voidTags.has(tagName) : false);
                const isComment = /^<!--/.test(trimmed);

                if (isClosing) indent = Math.max(indent - 1, 0);

                const padded = `${'  '.repeat(indent)}${trimmed}`;

                if (!isClosing && !selfClosing && !isComment && /^<[^!/?][^>]*>$/.test(trimmed) && !/<\/[^>]+>$/.test(trimmed)) {
                    indent += 1;
                }

                return padded;
            }).join('\n');
        },
        toggleSource() {
            this.sourceMode = !this.sourceMode;
            if (this.sourceMode) {
                this.syncFromEditor();
                this.content = this.prettyHtml(this.content);
                this.currentBlock = 'HTML';
                this.$nextTick(() => {
                    const textarea = this.fullscreen ? this.$refs.fullscreenTextarea : this.$refs.textarea;
                    if (textarea) textarea.focus();
                    this.syncCodeScroll();
                });
                return;
            }
            this.$nextTick(() => {
                const editor = this.fullscreen ? (this.$refs?.fullscreenEditor || null) : (this.$refs?.editor || null);
                this.syncEditorRefs();
                if (editor instanceof HTMLElement) editor.focus();
                this.updateCurrentBlock();
            });
        },
        toggleFullscreen() {
            this.fullscreen = !this.fullscreen;
            document.documentElement.classList.toggle('overflow-hidden', this.fullscreen);
            document.body.classList.toggle('overflow-hidden', this.fullscreen);
            this.$nextTick(() => {
                this.syncEditorRefs();
            });
        },
        toggleEmojiPicker() { this.closeMenus(); this.emojiOpen = !this.emojiOpen; this.saveSelection() },
        openDialog(type) {
            this.closeMenus();
            this.emojiOpen = false;
            this.saveSelection();
            this.dialogType = type;
            this.videoTab = 'files';
            this.dialogOpen = true;
            this.search = '';
            this.sort = 'newest';
            this.youtubeUrl = '';
            this.remoteUrl = '';
            this.mediaAlt = '';
            this.mediaCaption = '';
            this.showRemoteUrlForm = false;
            this.files = [];
            this.folders = [];
            this.breadcrumbs = [];
            this.currentFolder = null;
            this.page = 1;
            this.hasMore = true;
            this.loadFiles(true);
        },
        closeDialog() {
            this.dialogOpen = false;
            this.youtubeUrl = '';
            this.search = '';
            this.remoteUrl = '';
            this.mediaAlt = '';
            this.mediaCaption = '';
            this.showRemoteUrlForm = false;
            this.folders = [];
            this.breadcrumbs = [];
            this.currentFolder = null;
        },
        async loadFiles(reset = false) {
            if (!this.endpoint || (this.dialogType === 'video' && this.videoTab !== 'files') || this.loading) return;
            if (reset) { this.files = []; this.folders = []; this.page = 1; this.hasMore = true; }
            if (!this.hasMore) return;
            this.loading = true;
            try {
                const url = new URL(this.endpoint, window.location.origin);
                url.searchParams.set('page', String(this.page));
                url.searchParams.set('sort', this.sort);
                url.searchParams.set('type', this.dialogType === 'video' ? 'video' : 'image');
                if (this.currentFolder?.idSecure) url.searchParams.set('folder', this.currentFolder.idSecure);
                if (this.search.trim() !== '') url.searchParams.set('q', this.search.trim());
                const response = await fetch(url.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Failed');
                const payload = await response.json();
                const items = Array.isArray(payload.data) ? payload.data : [];
                this.folders = Array.isArray(payload.folders) ? payload.folders : [];
                this.breadcrumbs = Array.isArray(payload.breadcrumbs) ? payload.breadcrumbs : [];
                this.currentFolder = payload.current_folder || null;
                this.files = [...this.files, ...items];
                this.hasMore = !!payload.has_more;
                this.page = payload.next_page || (payload.has_more ? this.page + 1 : this.page);
            } catch (error) {
                this.hasMore = false;
            } finally {
                this.loading = false;
            }
        },
        openFolder(folder) {
            this.currentFolder = folder || null;
            this.search = '';
            this.loadFiles(true);
        },
        goToRoot() {
            this.currentFolder = null;
            this.search = '';
            this.loadFiles(true);
        },
        async uploadSelectedFiles(event) {
            const selected = Array.from(event.target.files || []);
            if (!selected.length || !this.uploadEndpoint) return;
            const formData = new FormData();
            selected.forEach((file) => formData.append('files[]', file));
            formData.append('type', this.dialogType === 'video' ? 'video' : 'image');
            if (this.currentFolder?.idSecure) formData.append('folder', this.currentFolder.idSecure);
            this.uploadingFiles = true;
            try {
                const response = await fetch(this.uploadEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: formData,
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload?.message || 'Upload failed.');
                const items = Array.isArray(payload.data) ? payload.data : [];
                this.files = [...items, ...this.files];
                if (items[0]) {
                    if (this.dialogType === 'video') this.insertVideo(items[0]);
                    else this.insertImage(items[0]);
                }
            } catch (error) {
            } finally {
                this.uploadingFiles = false;
                event.target.value = '';
            }
        },
        async importFromUrl() {
            if (!this.remoteUrl.trim() || !this.uploadFromUrlEndpoint) return;
            this.importingUrl = true;
            try {
                const url = new URL(this.uploadFromUrlEndpoint, window.location.origin);
                url.searchParams.set('type', this.dialogType === 'video' ? 'video' : 'image');
                if (this.currentFolder?.idSecure) url.searchParams.set('folder', this.currentFolder.idSecure);
                const response = await fetch(url.toString(), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ remote_url: this.remoteUrl.trim() }),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload?.message || 'Import failed.');
                if (payload.data) {
                    this.files = [payload.data, ...this.files];
                    this.showRemoteUrlForm = false;
                    this.remoteUrl = '';
                    if (this.dialogType === 'video') this.insertVideo(payload.data);
                    else this.insertImage(payload.data);
                }
            } catch (error) {
            } finally {
                this.importingUrl = false;
            }
        },
        handleScroll(target) {
            if (this.loading || !this.hasMore) return;
            if ((target.scrollTop + target.clientHeight) >= (target.scrollHeight - 180)) this.loadFiles();
        },
        escape(value) { return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;') },
        insertIntoTextarea(markup) {
            const t = this.$refs.textarea;
            if (!t) return;
            const s = t.selectionStart ?? t.value.length;
            const e = t.selectionEnd ?? t.value.length;
            t.focus();
            if (typeof t.setRangeText === 'function') t.setRangeText(markup, s, e, 'end');
            else t.value = t.value.slice(0, s) + markup + t.value.slice(e);
            t.dispatchEvent(new Event('input', { bubbles: true }));
            t.dispatchEvent(new Event('change', { bubbles: true }));
            this.syncFromTextarea();
        },
        insertMarkup(markup) {
            if (this.sourceMode) return this.insertIntoTextarea(markup);
            this.restoreSelection();
            document.execCommand('insertHTML', false, markup);
            this.syncFromEditor();
            this.saveSelection();
        },
        insertEmoji(emoji) { this.insertMarkup(emoji); this.emojiOpen = false },
        insertImage(file) {
            const src = file?.embedUrl || file?.previewUrl || file?.url;
            if (!src) return;
            const alt = this.escape(this.mediaAlt.trim() || file?.note || file?.name || '');
            const caption = this.escape(this.mediaCaption.trim() || file?.note || file?.name || '');
            this.insertMarkup(`<figure><img src="${this.escape(src)}" alt="${alt}">${caption ? `<figcaption>${caption}</figcaption>` : ''}</figure><p><br></p>`);
            this.closeDialog();
        },
        insertVideo(file) {
            const src = file?.embedUrl || file?.previewUrl || file?.url;
            if (!src) return;
            const caption = this.escape(this.mediaCaption.trim() || file?.note || file?.name || '');
            this.insertMarkup(`<figure><div class="embedded-video"><video controls preload="metadata" src="${this.escape(src)}"></video></div>${caption ? `<figcaption>${caption}</figcaption>` : ''}</figure><p><br></p>`);
            this.closeDialog();
        },
        youtubeEmbedUrl(value) {
            const input = String(value || '').trim();
            if (input === '') return '';
            try {
                const u = new URL(input);
                let id = '';
                if (u.hostname.includes('youtu.be')) id = u.pathname.replace(/^\/+/, '').split('/')[0] || '';
                else if (u.hostname.includes('youtube.com')) {
                    if (u.pathname === '/watch') id = u.searchParams.get('v') || '';
                    else if (u.pathname.startsWith('/embed/')) id = u.pathname.split('/embed/')[1]?.split('/')[0] || '';
                    else if (u.pathname.startsWith('/shorts/')) id = u.pathname.split('/shorts/')[1]?.split('/')[0] || '';
                }
                return id ? `https://www.youtube.com/embed/${id}` : '';
            } catch (error) { return ''; }
        },
        insertYoutube() {
            const src = this.youtubeEmbedUrl(this.youtubeUrl);
            if (!src) return;
            const caption = this.escape(this.mediaCaption.trim());
            this.insertMarkup(`<figure><div class="embedded-video embedded-video--youtube" style="position: relative; padding-top: 56.25%; overflow: hidden; border-radius: 1rem;"><iframe src="${this.escape(src)}" title="YouTube video" allowfullscreen loading="lazy" style="position: absolute; inset: 0; width: 100%; height: 100%; border: 0;"></iframe></div>${caption ? `<figcaption>${caption}</figcaption>` : ''}</figure><p><br></p>`);
            this.closeDialog();
        },
        setExternalContent(value) {
            this.content = String(value || '');

            if (this.$refs.textarea) {
                this.$refs.textarea.value = this.content;
                this.$refs.textarea.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.textarea.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (this.$refs.fullscreenTextarea) {
                this.$refs.fullscreenTextarea.value = this.content;
            }

            this.syncEditorRefs();

            this.updateCurrentBlock();
            this.notifyChange();
        },
        handleKeydown(event) {
            const key = String(event.key || '').toLowerCase();
            if (key === 'f11') { event.preventDefault(); return this.toggleFullscreen(); }
            if (key === 'escape' && this.fullscreen) { event.preventDefault(); return this.toggleFullscreen(); }
            if (event.ctrlKey && !event.shiftKey && key === '/') { event.preventDefault(); return this.toggleSource(); }
            if (this.sourceMode) return;
            if (event.altKey && !event.shiftKey && key === '0') { event.preventDefault(); return this.applyParagraph(); }
            if (event.altKey && !event.shiftKey && key === '1') { event.preventDefault(); return this.heading('H1'); }
            if (event.ctrlKey && !event.shiftKey && key === 'b') { event.preventDefault(); return this.format('bold'); }
            if (event.ctrlKey && !event.shiftKey && key === 'i') { event.preventDefault(); return this.format('italic'); }
            if (event.ctrlKey && !event.shiftKey && key === 'u') { event.preventDefault(); return this.format('underline'); }
            if (event.ctrlKey && !event.shiftKey && key === 'k') { event.preventDefault(); return this.createLink(); }
            if (event.ctrlKey && event.shiftKey && key === '7') { event.preventDefault(); return this.format('insertOrderedList'); }
            if (event.ctrlKey && event.shiftKey && key === '8') { event.preventDefault(); return this.format('insertUnorderedList'); }
            if (event.altKey && !event.shiftKey && key === '2') { event.preventDefault(); return this.heading('H2'); }
            if (event.altKey && !event.shiftKey && key === '3') { event.preventDefault(); return this.heading('H3'); }
            if (event.altKey && !event.shiftKey && key === '4') { event.preventDefault(); return this.heading('H4'); }
            if (event.altKey && !event.shiftKey && key === '5') { event.preventDefault(); return this.heading('H5'); }
            if (event.altKey && !event.shiftKey && key === '6') { event.preventDefault(); return this.heading('H6'); }
            if (event.altKey && !event.shiftKey && key === 'q') { event.preventDefault(); return this.insertBlockquote(); }
            if (event.altKey && event.shiftKey && key === 'c') { event.preventDefault(); return this.insertCodeBlock(); }
            if (event.altKey && event.shiftKey && key === 'h') { event.preventDefault(); return this.insertHorizontalRule(); }
            if (event.altKey && event.shiftKey && key === 'i') { event.preventDefault(); return this.openDialog('image'); }
            if (event.altKey && event.shiftKey && key === 'v') { event.preventDefault(); return this.openDialog('video'); }
            if (event.altKey && event.shiftKey && key === 'm') { event.preventDefault(); return this.toggleEmojiPicker(); }
            if (event.altKey && event.shiftKey && key === 'l') { event.preventDefault(); return this.format('justifyLeft'); }
            if (event.altKey && event.shiftKey && key === 'e') { event.preventDefault(); return this.format('justifyCenter'); }
            if (event.altKey && event.shiftKey && key === 'r') { event.preventDefault(); return this.format('justifyRight'); }
            if (event.altKey && event.shiftKey && key === 'j') { event.preventDefault(); return this.format('justifyFull'); }
            if (event.altKey && event.shiftKey && key === 'o') { event.preventDefault(); return this.format('outdent'); }
            if (event.altKey && event.shiftKey && key === 'p') { event.preventDefault(); return this.format('indent'); }
            if (event.altKey && event.shiftKey && key === 's') { event.preventDefault(); return this.format('strikeThrough'); }
        },
    };
};
</script>
@endonce
