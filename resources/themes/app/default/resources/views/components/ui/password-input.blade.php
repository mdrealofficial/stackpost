@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'placeholder' => null,
    'showToggle' => true,
    'revealed' => false,
])

<x-ui.field :help="$help" :error="$error" {{ $attributes->only('class') }}>
    @if ($label || isset($action))
        <div class="mb-2.5 flex items-center justify-between gap-3">
            @if ($label)
                <x-ui.label>{{ $label }}</x-ui.label>
            @else
                <span></span>
            @endif

            @isset($action)
                <div class="shrink-0">
                    {{ $action }}
                </div>
            @endisset
        </div>
    @endif

    <div x-data="{ revealed: @js((bool) $revealed) }">
        <x-ui.input-group>
            <input
                {{ $attributes->except('class')->merge(['placeholder' => $placeholder])->class('h-11 w-full border-0 bg-transparent px-4 text-sm font-medium outline-none placeholder:font-normal') }}
                x-bind:type="revealed ? 'text' : 'password'"
                style="color: var(--theme-input-text);"
            >

            @if ($showToggle)
                <x-slot:suffix>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-[0.65rem] transition hover:bg-black/5"
                        style="color: var(--theme-muted-text-color);"
                        x-on:click="revealed = !revealed"
                        aria-label="{{ __('Toggle password visibility') }}"
                    >
                        <i class="fa-light" x-bind:class="revealed ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </x-slot:suffix>
            @endif
        </x-ui.input-group>
    </div>
</x-ui.field>
