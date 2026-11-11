@props([
    'searchPlaceholder' => null,
    'headerItemsStart' => [],
    'headerItemsPrimaryNav' => [],
    'headerItemsCenter' => [],
    'headerItemsEnd' => [],
    'boxedLayout' => false,
    'pageTitle' => null,
    'area' => 'user',
    'homeHref' => null,
])

@php
    $middleNavItems = count($headerItemsCenter) > 0 ? $headerItemsCenter : $headerItemsPrimaryNav;
@endphp

<header class="app-default-header sticky top-0 z-40 border-b border-slate-200/80 dark:border-slate-800" style="background-color: var(--theme-header-surface); border-color: color-mix(in srgb, var(--theme-shell-border-color) 78%, transparent);">
    <div class="relative mx-auto flex min-h-16 w-full items-center px-2 sm:px-6 xl:px-8" @class([
        'app-theme-shell' => ! $boxedLayout && $area !== 'admin',
        'max-w-[1440px]' => $boxedLayout,
        'max-w-none' => ! $boxedLayout && $area === 'admin',
    ])>
        <div class="sp-header-start flex min-w-0 items-center">
            @if (isset($start))
                <div class="lg:hidden">
                    {{ $start }}
                </div>
            @endif

            @if (isset($workspace))
                <div class="sp-header-workspace">{{ $workspace }}</div>
            @elseif (count($middleNavItems) === 0)
                <div class="sp-header-context"><a href="{{ $homeHref }}" wire:navigate>{{ $area === 'admin' ? __('Admin') : __('Workspace') }}</a><span aria-hidden="true">/</span><strong>{{ $pageTitle ?: __('Overview') }}</strong></div>
            @endif

            @if (count($middleNavItems) > 0)
                <div class="ml-2 xl:hidden">
                    <x-ui.dropdown-menu align="left" width="auto">
                        <x-slot:trigger>
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-[0.75rem] border border-slate-200 text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);"
                                aria-label="{{ __('Open workspace navigation') }}"
                            >
                                <i class="fa-light fa-rectangle-list text-[15px]"></i>
                            </button>
                        </x-slot:trigger>

                        @foreach ($middleNavItems as $item)
                            @if (! empty($item['html']))
                                {!! $item['html'] !!}
                            @elseif (! empty($item['view']))
                                @include($item['view'], $item['data'] ?? [])
                            @elseif (! empty($item['route']) || ! empty($item['label']) || ! empty($item['icon']))
                                @php
                                    $dropdownHref = $item['route'] ?? '#';
                                    $dropdownIcon = ! empty($item['icon']) ? $item['icon'] : null;
                                    $dropdownNavigate = ! empty($item['route']);
                                @endphp
                                @if ($dropdownNavigate)
                                    <x-ui.dropdown-menu-item
                                        href="{{ $dropdownHref }}"
                                        wire:navigate
                                        :icon="$dropdownIcon"
                                    >
                                        {{ __($item['label'] ?? '') }}
                                    </x-ui.dropdown-menu-item>
                                @else
                                    <x-ui.dropdown-menu-item
                                        href="{{ $dropdownHref }}"
                                        :icon="$dropdownIcon"
                                    >
                                        {{ __($item['label'] ?? '') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                            @endif
                        @endforeach
                    </x-ui.dropdown-menu>
                </div>
            @endif

            @if (count($headerItemsStart) > 0)
                <div class="hidden min-w-0 items-center gap-3 lg:flex lg:border-r lg:border-slate-200/80 lg:pr-4 dark:lg:border-slate-800/90" style="border-color: var(--theme-shell-border-color);">
                    @foreach ($headerItemsStart as $item)
                        @if (! empty($item['html']))
                            {!! $item['html'] !!}
                        @elseif (! empty($item['view']))
                            @include($item['view'], $item['data'] ?? [])
                        @elseif (! empty($item['route']) || ! empty($item['label']) || ! empty($item['icon']))
                            @php
                                $itemHref = $item['route'] ?? '#';
                                $itemIsActive = (bool) ($item['active'] ?? false);
                                $itemTitle = $item['title'] ?? $item['label'] ?? null;
                                $itemActiveClasses = $itemIsActive
                                    ? 'bg-[var(--sp-nav-active)] text-[var(--theme-accent)] dark:bg-[var(--sp-nav-active)] dark:text-[var(--theme-accent)]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-white';
                                $itemClasses = $itemActiveClasses.' inline-flex h-11 items-center gap-2 rounded-[0.75rem] border border-slate-200/80 px-3 text-sm font-semibold transition dark:border-slate-700';
                                $itemStyle = $itemIsActive
                                    ? 'background-color: var(--sp-nav-active); color: var(--theme-accent); border-color: transparent;'
                                    : 'background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);';
                            @endphp
                            @if (! empty($item['route']))
                                <a
                                    href="{{ $itemHref }}"
                                    class="{{ $itemClasses }}"
                                    style="{{ $itemStyle }}"
                                    @if ($itemTitle) title="{{ __($itemTitle) }}" aria-label="{{ __($itemTitle) }}" @endif
                                    wire:navigate
                                >
                                    @if (! empty($item['icon']))
                                        <i class="{{ $item['icon'] }} text-[15px]"></i>
                                    @endif
                                    @if (! empty($item['label']))
                                        <span>{{ __($item['label']) }}</span>
                                    @endif
                                </a>
                            @else
                                <a
                                    href="{{ $itemHref }}"
                                    class="{{ $itemClasses }}"
                                    style="{{ $itemStyle }}"
                                    @if ($itemTitle) title="{{ __($itemTitle) }}" aria-label="{{ __($itemTitle) }}" @endif
                                >
                                    @if (! empty($item['icon']))
                                        <i class="{{ $item['icon'] }} text-[15px]"></i>
                                    @endif
                                    @if (! empty($item['label']))
                                        <span>{{ __($item['label']) }}</span>
                                    @endif
                                </a>
                            @endif
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if (count($middleNavItems) > 0)
            <div class="{{ isset($workspace) ? 'hidden min-w-0 flex-1 items-center justify-center px-4 xl:flex' : 'absolute left-1/2 top-1/2 hidden min-w-0 -translate-x-1/2 -translate-y-1/2 items-center justify-center px-4 xl:flex' }}">
                <nav class="flex min-w-0 items-center gap-1 overflow-x-auto rounded-[0.75rem] border border-slate-200/70 p-1.5 dark:border-slate-800" style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);">
                    @foreach ($middleNavItems as $item)
                        @if (! empty($item['html']))
                            {!! $item['html'] !!}
                        @elseif (! empty($item['view']))
                            @include($item['view'], $item['data'] ?? [])
                        @elseif (! empty($item['route']) || ! empty($item['label']) || ! empty($item['icon']))
                            @php
                                $itemHref = $item['route'] ?? '#';
                                $itemActiveClasses = ($item['active'] ?? false)
                                    ? 'bg-[var(--sp-nav-active)] text-[var(--theme-accent)] dark:bg-[var(--sp-nav-active)] dark:text-[var(--theme-accent)]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-white';
                                $itemClasses = $itemActiveClasses.' inline-flex items-center gap-2 rounded-[0.65rem] px-3.5 py-2 text-sm font-semibold transition';
                            @endphp
                            @if (! empty($item['route']))
                                <a
                                    href="{{ $itemHref }}"
                                    class="{{ $itemClasses }}"
                                    wire:navigate
                                >
                                    @if (! empty($item['icon']))
                                        <i class="{{ $item['icon'] }} text-[15px]"></i>
                                    @endif
                                    @if (! empty($item['label']))
                                        <span>{{ __($item['label']) }}</span>
                                    @endif
                                </a>
                            @else
                                <a
                                    href="{{ $itemHref }}"
                                    class="{{ $itemClasses }}"
                                >
                                    @if (! empty($item['icon']))
                                        <i class="{{ $item['icon'] }} text-[15px]"></i>
                                    @endif
                                    @if (! empty($item['label']))
                                        <span>{{ __($item['label']) }}</span>
                                    @endif
                                </a>
                            @endif
                        @endif
                    @endforeach
                </nav>
            </div>
        @endif

        <div class="ml-auto flex items-center gap-2.5 md:gap-3">
            @if (trim((string) ($center ?? '')) !== '')
                <div class="hidden h-11 items-center gap-1 rounded-[0.75rem] border border-slate-200/70 px-1.5 dark:border-slate-800 md:flex" style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);">
                    {{ $center ?? '' }}
                </div>
            @endif

            <div class="flex items-center gap-2.5">
                {{ $end ?? '' }}

                @foreach ($headerItemsEnd as $item)
                    @if (! empty($item['html']))
                        {!! $item['html'] !!}
                    @elseif (! empty($item['view']))
                        @include($item['view'], $item['data'] ?? [])
                    @elseif (! empty($item['route']) || ! empty($item['label']) || ! empty($item['icon']))
                        @php
                            $itemHref = $item['route'] ?? '#';
                            $itemIsActive = (bool) ($item['active'] ?? false);
                            $itemTitle = $item['title'] ?? $item['label'] ?? null;
                            $itemActiveClasses = $itemIsActive
                                ? 'bg-[var(--sp-nav-active)] text-[var(--theme-accent)] dark:bg-[var(--sp-nav-active)] dark:text-[var(--theme-accent)]'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-white';
                            $itemClasses = $itemActiveClasses.' inline-flex h-11 items-center gap-2 rounded-[0.75rem] border border-slate-200/80 px-3.5 text-sm font-semibold transition dark:border-slate-700';
                            $itemStyle = $itemIsActive
                                ? 'background-color: var(--sp-nav-active); color: var(--theme-accent); border-color: transparent;'
                                : 'background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);';
                        @endphp
                        @if (! empty($item['route']))
                            <a
                                href="{{ $itemHref }}"
                                class="{{ $itemClasses }}"
                                style="{{ $itemStyle }}"
                                @if ($itemTitle) title="{{ __($itemTitle) }}" aria-label="{{ __($itemTitle) }}" @endif
                                wire:navigate
                            >
                                @if (! empty($item['icon']))
                                    <i class="{{ $item['icon'] }} text-[15px]"></i>
                                @endif
                                @if (! empty($item['label']))
                                    <span>{{ __($item['label']) }}</span>
                                @endif
                            </a>
                        @else
                            <a
                                href="{{ $itemHref }}"
                                class="{{ $itemClasses }}"
                                style="{{ $itemStyle }}"
                                @if ($itemTitle) title="{{ __($itemTitle) }}" aria-label="{{ __($itemTitle) }}" @endif
                            >
                                @if (! empty($item['icon']))
                                    <i class="{{ $item['icon'] }} text-[15px]"></i>
                                @endif
                                @if (! empty($item['label']))
                                    <span>{{ __($item['label']) }}</span>
                                @endif
                            </a>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</header>
