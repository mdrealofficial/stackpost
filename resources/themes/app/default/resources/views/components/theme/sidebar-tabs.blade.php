@props([
    'libraryCount' => 0,
    'customizeCount' => 0,
    'libraryDescription' => __('Browse available themes'),
    'customizeDescription' => __('Tune branding and layout tokens'),
])

<div {{ $attributes->class('rounded-[0.95rem] border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-950') }}>
    <button
        type="button"
        @click="themeTab = 'select-theme'"
        :class="themeTab === 'select-theme'
            ? 'text-white shadow-[0_14px_28px_-18px_rgba(var(--theme-accent-rgb),0.55)]'
            : 'border-transparent text-slate-600 hover:border-[color:rgba(var(--theme-accent-rgb),0.18)] hover:bg-[color:rgba(var(--theme-accent-rgb),0.08)] hover:text-[var(--theme-header-text-color)] dark:text-slate-400 dark:hover:border-[color:rgba(var(--theme-accent-rgb),0.22)] dark:hover:bg-[color:rgba(var(--theme-accent-rgb),0.14)] dark:hover:text-white'"
        :style="themeTab === 'select-theme' ? 'border-color: var(--theme-accent); background-color: var(--theme-accent); color: #fff;' : ''"
        class="flex w-full items-start justify-between rounded-[0.8rem] border px-4 py-3 text-left transition"
    >
        <span>
            <span class="block text-sm font-semibold">{{ __('Library') }}</span>
            <span class="mt-1 block text-xs leading-5 opacity-80">{{ $libraryDescription }}</span>
        </span>
        <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold dark:bg-slate-900/20">{{ $libraryCount }}</span>
    </button>

    <button
        type="button"
        @click="themeTab = 'theme-settings'"
        :class="themeTab === 'theme-settings'
            ? 'text-white shadow-[0_14px_28px_-18px_rgba(var(--theme-accent-rgb),0.55)]'
            : 'border-transparent text-slate-600 hover:border-[color:rgba(var(--theme-accent-rgb),0.18)] hover:bg-[color:rgba(var(--theme-accent-rgb),0.08)] hover:text-[var(--theme-header-text-color)] dark:text-slate-400 dark:hover:border-[color:rgba(var(--theme-accent-rgb),0.22)] dark:hover:bg-[color:rgba(var(--theme-accent-rgb),0.14)] dark:hover:text-white'"
        :style="themeTab === 'theme-settings' ? 'border-color: var(--theme-accent); background-color: var(--theme-accent); color: #fff;' : ''"
        class="mt-2 flex w-full items-start justify-between rounded-[0.8rem] border px-4 py-3 text-left transition"
    >
        <span>
            <span class="block text-sm font-semibold">{{ __('Customize') }}</span>
            <span class="mt-1 block text-xs leading-5 opacity-80">{{ $customizeDescription }}</span>
        </span>
        <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold dark:bg-slate-900/20">{{ $customizeCount }}</span>
    </button>
</div>
