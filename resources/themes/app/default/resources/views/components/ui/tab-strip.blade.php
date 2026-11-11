@props(['items' => [], 'model', 'label' => 'Sections'])
<div role="tablist" aria-label="{{ __($label) }}" {{ $attributes->class('flex gap-1 overflow-x-auto rounded-lg border p-1') }} style="border-color:var(--theme-border-color);background:var(--theme-surface-base)"
    x-on:keydown.home.prevent="const tab = $el.querySelector('[role=tab]'); tab?.focus(); tab?.click()"
    x-on:keydown.end.prevent="const tabs = $el.querySelectorAll('[role=tab]'); const tab = tabs[tabs.length-1]; tab?.focus(); tab?.click()"
    x-on:keydown.arrow-right.prevent="const tabs = [...$el.querySelectorAll('[role=tab]')]; const next = tabs[(tabs.indexOf(document.activeElement)+1)%tabs.length]; next?.focus(); next?.click()"
    x-on:keydown.arrow-left.prevent="const tabs = [...$el.querySelectorAll('[role=tab]')]; const next = tabs[(tabs.indexOf(document.activeElement)-1+tabs.length)%tabs.length]; next?.focus(); next?.click()">
    @foreach($items as $item)
    <button type="button" role="tab" class="shrink-0 rounded-md px-4 py-2 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
        x-on:click="{{ $model }} = @js($item['key'])"
        x-bind:aria-selected="{{ $model }} === @js($item['key'])"
        x-bind:tabindex="{{ $model }} === @js($item['key']) ? 0 : -1"
        x-bind:style="{{ $model }} === @js($item['key']) ? 'background:var(--theme-surface-soft);color:var(--theme-accent)' : 'color:var(--theme-muted-text-color)'">{{ $item['label'] }}</button>
    @endforeach
</div>
