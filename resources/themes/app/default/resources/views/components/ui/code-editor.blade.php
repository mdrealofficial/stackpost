@props([
    'name',
    'label',
    'value' => '',
    'mode' => 'css',
    'rows' => 10,
])

<label {{ $attributes->class('block') }}>
    <span class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
    <textarea
        name="{{ $name }}"
        rows="{{ $rows }}"
        spellcheck="false"
        data-code-editor="{{ $mode }}"
        class="w-full rounded-[0.8rem] border border-slate-200 bg-slate-950 px-4 py-3.5 font-mono text-[13px] leading-6 text-slate-100 outline-none transition duration-200 placeholder:text-slate-500 focus:border-[var(--theme-accent,#4f46e5)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.12)] dark:border-slate-700 dark:bg-[#020617] dark:text-slate-100"
        style="tab-size: 4;"
    >{{ $value }}</textarea>
</label>
