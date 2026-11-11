@props([
    'align' => 'left',
    'width' => '56',
    'panelClass' => '',
])

@php
    $alignClass = $align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left';
    $widthClass = match ($width) {
        'auto' => 'w-max min-w-[9.25rem]',
        'full' => 'w-full',
        default => 'w-'.$width,
    };
@endphp

<div x-data="{
        open: false,
        trigger() { return this.$refs.trigger.querySelector('button, a, [tabindex]'); },
        entries() { return [...this.$refs.panel.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), [tabindex]')].filter(el => el.offsetParent !== null &amp;&amp; el.tabIndex >= 0); },
        close(restore = false) { this.open = false; if (restore) this.trigger()?.focus(); },
        move(step) { const items = this.entries(); if (!items.length) return; const index = items.indexOf(document.activeElement); items[(index + step + items.length) % items.length]?.focus(); },
        reveal(last = false) { this.open = true; this.$nextTick(() => { const items = this.entries(); (last ? items[items.length - 1] : items[0])?.focus(); }); }
    }" x-id="['dropdown-panel']" x-effect="const triggerEl = trigger(); if (triggerEl) { triggerEl.setAttribute('aria-expanded', String(open)); triggerEl.setAttribute('aria-controls', $id('dropdown-panel')); }"
    x-bind:class="open ? 'z-[60]' : 'z-0'" x-on:click.stop
    x-on:keydown.escape.stop.prevent="close(true)"
    x-on:focusout="if (!$el.contains($event.relatedTarget)) close()"
    {{ $attributes->class('relative inline-block text-left') }}>
    <div x-ref="trigger" x-on:click.stop="open = !open" x-on:keydown.arrow-down.stop.prevent="reveal()" x-on:keydown.arrow-up.stop.prevent="reveal(true)">
        {{ $trigger ?? '' }}
    </div>

    <div
        x-ref="panel"
        x-bind:id="$id('dropdown-panel')"
        x-on:keydown.arrow-down.stop.prevent="move(1)"
        x-on:keydown.arrow-up.stop.prevent="move(-1)"
        x-cloak
        x-show="open"
        x-transition.opacity
        x-on:click.stop
        x-on:click.outside="open = false"
        
        class="absolute {{ $alignClass }} z-50 mt-2 {{ $widthClass }} {{ $panelClass }} overflow-hidden rounded-[1.1rem] border p-1.5 shadow-[0_18px_38px_-18px_rgba(15,23,42,0.22)]"
        style="border-color: color-mix(in srgb, var(--theme-border-color) 78%, transparent 22%); background-color: var(--theme-surface-base);"
    >
        {{ $slot }}
    </div>
</div>
