@props([
    'inputName',
    'keyName',
    'label',
    'value' => '#ffffff',
    'presets' => [],
    'previewState' => 'preview',
    'pickerRef',
])

<div {{ $attributes->class('rounded-[0.9rem] border border-slate-200/85 bg-white p-4 shadow-[0_12px_34px_-28px_rgba(15,23,42,0.14)] dark:border-slate-800 dark:bg-slate-950/60') }}>
    <div class="flex items-start gap-4">
        <div class="relative shrink-0">
            <input type="hidden" name="{{ $inputName }}" x-model="{{ $previewState }}['{{ $keyName }}']">
            <input type="color" x-model="{{ $previewState }}['{{ $keyName }}']" x-ref="{{ $pickerRef }}" class="absolute inset-0 h-14 w-14 cursor-pointer opacity-0">
            <button type="button" @click="$refs.{{ $pickerRef }}.click()" class="flex h-14 w-14 items-center justify-center rounded-[0.9rem] border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <span class="h-9 w-9 rounded-[0.55rem] border border-white/70 shadow-inner shadow-black/5" :style="{ backgroundColor: {{ $previewState }}['{{ $keyName }}'] || '{{ $value }}' }"></span>
            </button>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $label }}</p>
                </div>
                <span class="rounded-full border border-slate-200/80 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500">{{ __('Live') }}</span>
            </div>
            <input type="text" x-model="{{ $previewState }}['{{ $keyName }}']" class="mt-3 h-10 w-full rounded-[0.65rem] border border-slate-200 bg-white px-3 font-mono text-sm uppercase tracking-[0.08em] text-slate-700 outline-none focus:border-[var(--theme-accent,#4f46e5)] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
        </div>
    </div>

    @if (! empty($presets))
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($presets as $preset)
                <button type="button" @click="{{ $previewState }}['{{ $keyName }}'] = '{{ $preset }}'" class="group inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-white">
                    <span class="size-4 rounded-full border border-black/5 shadow-inner" style="background-color: {{ $preset }}"></span>
                    <span>{{ $preset }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
