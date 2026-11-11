@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'pickerTitle' => __('Emojis'),
    'pickerPosition' => 'bottom',
    'pickerAlign' => 'left',
    'triggerPosition' => 'inside-right',
])

@php
    $emojiGroups = [
        ['key' => 'recent', 'title' => __('Recent'), 'icon' => '🕘', 'items' => ['😀', '😂', '😊', '😍', '🥳', '😎', '👍', '🙏', '🔥', '🎉', '❤️', '✅']],
        ['key' => 'smileys', 'title' => __('Smileys & People'), 'icon' => '😊', 'items' => ['😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😆', '😉', '😊', '🙂', '🙃', '😍', '🥰', '😘', '😗', '😋', '😜', '🤪', '🤩', '🥳', '😎', '🤓', '🧐', '🤔', '🤗', '🫡', '🤭', '🤫', '😴', '😭', '😤', '😡', '🤯', '😱', '🥶', '🥵', '😇', '🙄', '😬', '😮', '😲', '😳', '🥺', '😢', '🤤', '😵', '🤐', '👋', '🤚', '🖐️', '✋', '🫱', '🫲', '👌', '🤌', '🤏', '✌️', '🤞', '🫰', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '👍', '👎', '👏', '🙌', '👐', '🤝', '🙏', '💪', '🫶', '👀']],
        ['key' => 'animals', 'title' => __('Animals & Nature'), 'icon' => '🐻', 'items' => ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🐤', '🦆', '🦅', '🦉', '🐺', '🐗', '🐴', '🦄', '🐝', '🪲', '🦋', '🐌', '🐞', '🐢', '🐍', '🦎', '🦀', '🐙', '🐬', '🐳', '🦈', '🐊', '🌵', '🌲', '🌳', '🌴', '🌱', '🌿', '☘️', '🍀', '🌷', '🌹', '🌻', '🌼', '🌸', '🌺', '🍁', '🍂', '🍄', '🌞', '🌝', '🌈', '⚡', '❄️', '☀️']],
        ['key' => 'food', 'title' => __('Food & Drink'), 'icon' => '🍕', 'items' => ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🥑', '🥦', '🥬', '🥒', '🌽', '🥕', '🫒', '🍄', '🥔', '🍠', '🍞', '🥐', '🥖', '🫓', '🥨', '🧀', '🥚', '🍳', '🥓', '🍔', '🍟', '🍕', '🌭', '🥪', '🌮', '🌯', '🫔', '🥗', '🍝', '🍜', '🍲', '🍛', '🍣', '🍤', '🍱', '🥟', '🍦', '🍩', '🍪', '🎂', '🍰', '🧁', '🍫', '🍬', '🍿', '☕', '🍵', '🧃', '🥤', '🍺', '🍷', '🍸']],
        ['key' => 'travel', 'title' => __('Travel & Places'), 'icon' => '✈️', 'items' => ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚚', '🚜', '🛵', '🏍️', '🚲', '✈️', '🛫', '🛬', '🚀', '🛸', '🚁', '⛵', '🚤', '🛳️', '🚢', '🏠', '🏡', '🏢', '🏭', '🏬', '🏰', '🗼', '🗽', '⛪', '🕌', '🛕', '⛰️', '🏔️', '🗻', '🏕️', '🏖️', '🏝️', '🌁', '🌃', '🌆', '🌇', '🌉', '🌌', '🎑']],
        ['key' => 'activity', 'title' => __('Activity'), 'icon' => '⚽', 'items' => ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🏓', '🏸', '🥊', '🥋', '🎯', '⛳', '🎣', '🤿', '🎽', '🛹', '🛷', '⛸️', '🥌', '🎮', '🕹️', '🎲', '♟️', '🎨', '🎭', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎸', '🎻', '🎬', '📸']],
        ['key' => 'objects', 'title' => __('Objects'), 'icon' => '💡', 'items' => ['⌚', '📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '📷', '📹', '🎥', '📞', '☎️', '📺', '📻', '⏰', '⏳', '📡', '🔋', '🔌', '💡', '🔦', '🕯️', '🧯', '💸', '💵', '💴', '💶', '💷', '🪙', '💳', '💎', '⚖️', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪓', '🔩', '⚙️', '🧰', '🧲', '🧪', '🧫', '🧬', '💉', '💊', '🩹', '📌', '📍', '✂️', '🖊️', '🖍️', '📝', '📚', '📦', '📓', '🗑️', '🔒', '🔑', '❤️', '💔', '⭐', '🌟', '✨', '✅', '❌', '⚠️', '🚫', '❗', '❓']],
    ];

    $inputClasses = 'flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]';

    if ($triggerPosition === 'inside-right') {
        $inputClasses .= ' pr-14';
    }
@endphp

<div
    x-data="{
        pickerOpen: false,
        search: '',
        activeGroup: 'recent',
        pickerStyle: '',
        updatePickerPosition() {
            if (!this.pickerOpen) return;
            const trigger = this.$refs.triggerButton;
            if (!trigger) return;

            const rect = trigger.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const width = Math.min(512, viewportWidth - 32);
            const gap = 12;

            let left = {{ $pickerAlign === 'right' ? 'rect.right - width' : 'rect.left' }};
            left = Math.max(16, Math.min(left, viewportWidth - width - 16));

            const vertical = {{ $pickerPosition === 'top'
                ? "'bottom:' + Math.max(16, viewportHeight - rect.top + gap) + 'px;'"
                : "'top:' + Math.max(16, rect.bottom + gap) + 'px;'" }};

            this.pickerStyle = `position: fixed; left: ${left}px; width: ${width}px; ${vertical} border-color: var(--theme-border-color); background-color: var(--theme-surface-base); opacity: 1;`;
        },
        togglePicker() {
            this.pickerOpen = !this.pickerOpen;
            if (this.pickerOpen) {
                this.$nextTick(() => this.updatePickerPosition());
            }
        },
        insertEmoji(emoji) {
            const input = this.$refs.input;
            if (!input) return;

            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? input.value.length;

            input.focus();

            if (typeof input.setRangeText === 'function') {
                input.setRangeText(emoji, start, end, 'end');
            } else {
                input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
                const nextPosition = start + emoji.length;
                input.setSelectionRange(nextPosition, nextPosition);
            }

            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            this.updatePickerPosition();
        }
    }"
    x-on:resize.window="updatePickerPosition()"
    x-on:scroll.window="updatePickerPosition()"
    x-on:keydown.escape.window="pickerOpen = false"
    {{ $attributes->only('class')->class('space-y-2.5') }}
>
    @if ($label)
        <x-ui.label>{{ $label }}</x-ui.label>
    @endif

    <div class="space-y-3">
        <div class="relative">
            <input
                x-ref="input"
                {{ $attributes->merge(['style' => 'border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);'])->except('class')->class($inputClasses) }}
            >

            @if ($triggerPosition === 'inside-right')
                <div class="absolute inset-y-0 right-3 flex items-center" x-on:click.outside="pickerOpen = false">
                    <x-ui.button x-ref="triggerButton" type="button" variant="outline" size="sm" class="!h-9 !w-9 !rounded-full !p-0" x-on:click="togglePicker()" :title="__('Emoji')">
                        <i class="fa-light fa-face-smile text-base"></i>
                    </x-ui.button>
                </div>
            @endif
        </div>

        @if ($triggerPosition !== 'inside-right')
            <div class="relative" x-on:click.outside="pickerOpen = false">
                <x-ui.button x-ref="triggerButton" type="button" variant="outline" size="sm" x-on:click="togglePicker()">
                    <i class="fa-light fa-face-smile text-lg"></i>
                    {{ __('Emoji') }}
                </x-ui.button>
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="pickerOpen"
            x-transition.opacity.scale.95
            x-on:click.outside="pickerOpen = false"
            x-bind:style="pickerStyle"
            class="z-[140] overflow-hidden rounded-[1rem] border shadow-[0_18px_42px_-28px_rgba(15,23,42,0.28)]"
        >
            <div class="border-b px-4 py-3" style="border-color: var(--theme-border-color);">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $pickerTitle }}</p>
                    <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="pickerOpen = false">
                        <i class="fa-light fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-3">
                    <input
                        x-model="search"
                        type="text"
                        class="flex h-10 w-full border px-3 text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                        style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                        placeholder="{{ __('Search emoji...') }}"
                    >
                </div>
            </div>

            <div class="border-b px-3 py-2" style="border-color: var(--theme-border-color);">
                <div class="flex flex-wrap gap-2">
                    @foreach ($emojiGroups as $group)
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] border text-lg transition hover:bg-slate-50 dark:hover:bg-slate-800/70"
                            style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent);"
                            x-bind:class="activeGroup === @js($group['key']) && search === '' ? 'bg-[rgba(var(--theme-accent-rgb),0.10)] border-[var(--theme-accent)]' : ''"
                            x-on:click="activeGroup = @js($group['key']); search = ''"
                            title="{{ $group['title'] }}"
                        >
                            {{ $group['icon'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="max-h-80 overflow-y-auto px-4 py-4">
                @foreach ($emojiGroups as $group)
                    <div class="space-y-3" x-show="search !== '' || activeGroup === @js($group['key'])" x-cloak>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ $group['title'] }}</p>
                        <div class="grid grid-cols-8 gap-2">
                            @foreach ($group['items'] as $emoji)
                                <button
                                    type="button"
                                    class="inline-flex h-11 items-center justify-center rounded-[0.85rem] border text-2xl transition hover:scale-[1.03] hover:bg-slate-50 dark:hover:bg-slate-800/70"
                                    style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent);"
                                    x-show="search === '' || @js(mb_strtolower($group['title'].' '.$emoji)).includes(search.toLowerCase())"
                                    x-on:click="insertEmoji(@js($emoji))"
                                >
                                    {{ $emoji }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </template>

    @if ($error)
        <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
    @elseif ($help)
        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $help }}</p>
    @endif
</div>
