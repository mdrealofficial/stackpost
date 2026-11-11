@php
        $userCollection = $users->getCollection();
        $loadedUsers = $userCollection->count();
        $totalUsers = $users->total();
        $latestUser = $userCollection->first();
        $recentUsers = $userCollection->filter(fn ($user) => optional($user->created_at)?->isAfter(now()->subDays(7)))->count();
        $verifiedUsers = $userCollection->filter(fn ($user) => filled($user->email))->count();
        $usersWithRoles = $userCollection->filter(fn ($user) => filled($user->role_id))->count();
        $usersWithPlans = $userCollection->filter(fn ($user) => filled($user->plan_id))->count();
        $priorityUsers = $userCollection->filter(fn ($user) => blank($user->email) || blank($user->role_id) || blank($user->plan_id))->count();
        $localeCoverage = $userCollection->pluck('locale')->filter()->unique()->count();
        $completionRate = $loadedUsers > 0 ? (int) round(($verifiedUsers / $loadedUsers) * 100) : 0;
        $roleCoverage = $loadedUsers > 0 ? (int) round(($usersWithRoles / $loadedUsers) * 100) : 0;
        $planCoverage = $loadedUsers > 0 ? (int) round(($usersWithPlans / $loadedUsers) * 100) : 0;
        $authUser = auth()->user();

        $metricCards = [
            ['label' => __('Visible users'), 'value' => number_format($totalUsers), 'description' => __('Users matching the current filters.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
            ['label' => __('Recent signups'), 'value' => number_format($recentUsers), 'description' => __('On this page, last 7 days.'), 'tone' => '#10b981', 'progress' => $loadedUsers > 0 ? max(8, (int) round(($recentUsers / $loadedUsers) * 100)) : 8],
            ['label' => __('Role coverage'), 'value' => $roleCoverage.'%', 'description' => number_format($usersWithRoles).' '.__('mapped accounts on this page'), 'tone' => '#f59e0b', 'progress' => max(8, $roleCoverage)],
            ['label' => __('Plan coverage'), 'value' => $planCoverage.'%', 'description' => number_format($usersWithPlans).' '.__('accounts with plans on this page'), 'tone' => '#64748b', 'progress' => max(8, $planCoverage)],
        ];

        $controlLinks = [
            ['title' => __('Roles'), 'description' => __('Permission groups and access mapping'), 'href' => route('admin-user-roles.index'), 'icon' => 'fa-light fa-shield-keyhole'],
            ['title' => __('Teams'), 'description' => __('Ownership structure and member allocation'), 'href' => route('admin-user-teams.index'), 'icon' => 'fa-light fa-people-group'],
            ['title' => __('Reports'), 'description' => __('Growth, verification, and distribution signals'), 'href' => route('admin-user-report.index'), 'icon' => 'fa-light fa-chart-line'],
            ['title' => __('Audit logs'), 'description' => __('Identity and admin actions'), 'href' => route('admin-user-logs.index'), 'icon' => 'fa-light fa-clipboard-list-check'],
        ];

        $sortIcon = function (string $key) use ($filters): string {
            if (($filters['sort'] ?? 'created') !== $key) {
                return 'fa-light fa-arrows-up-down';
            }

            return ($filters['direction'] ?? 'desc') === 'asc'
                ? 'fa-light fa-arrow-up-wide-short'
                : 'fa-light fa-arrow-down-wide-short';
        };

    @endphp

<div class="mx-auto max-w-[88rem] space-y-6">

        <x-ui.page-hero
            :eyebrow="__('Access workspace')"
            :title="__('Admin Users')"
            :description="__('Manage accounts, access, and plan assignments.')"
            :count="$totalUsers"
            icon="fa-light fa-users-gear"
        >
            <x-slot:actions>
                <x-ui.button href="{{ route('admin-users.create') }}" wire:navigate>{{ __('Create user') }}</x-ui.button>
                <x-ui.button href="{{ route('admin-user-report.index') }}" variant="outline" wire:navigate>{{ __('Reports') }}</x-ui.button>
                <x-ui.button href="{{ route('dashboard') }}" variant="outline" wire:navigate>{{ __('Dashboard') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-hero>

        <x-ui.metric-strip :items="$metricCards" :show-icons="false" :show-progress="false" columns="md:grid-cols-2 xl:grid-cols-4" />

        <nav class="sp-directory-links" aria-label="{{ __('User management shortcuts') }}">
            @foreach ($controlLinks as $link)
                <a href="{{ $link['href'] }}" title="{{ $link['description'] }}" wire:navigate><i class="{{ $link['icon'] }}" aria-hidden="true"></i>{{ $link['title'] }}<i class="fa-light fa-arrow-up-right" aria-hidden="true"></i></a>
            @endforeach
        </nav>

    <div
        x-data="{
            selectedIds: $wire.entangle('selectedUserIds').live,
            get selectedCount() {
                return this.selectedIds.length;
            },
            toggleAll(event) {
                this.selectedIds = event.target.checked ? @js($users->pluck('id')->map(fn ($id) => (string) $id)->values()->all()) : [];
            },
        }"
    >
        <x-ui.datatable-shell
                :title="__('User directory')"
                :info="null"
                header-class="py-4"
                eyebrow-class="text-[10px] tracking-[0.2em]"
                title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
                description-class="mt-1 leading-6"
                :footer-text="$users->hasPages()
                    ? null
                    : ($users->total() > 0
                        ? __('Showing :from to :to of :total records', ['from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()])
                        : __('No records found.'))"
            >
            <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                <div class="flex flex-col gap-4">
                    <div class="grid gap-3 xl:grid-cols-[minmax(0,1.5fr)_240px_auto]">
                        <x-ui.input
                            wire:model.live.debounce.300ms="q"
                            :label="__('Search')"
                            :placeholder="__('Name, username, or email...')"
                        />

                        <x-ui.select wire:model.live="status" :label="__('Filter')">
                            <option value="all">{{ __('All users') }}</option>
                            <option value="review">{{ __('Need review') }}</option>
                            <option value="ready">{{ __('Ready only') }}</option>
                        </x-ui.select>

                        <div class="flex flex-wrap items-end gap-3 xl:justify-end">
                            <x-ui.button type="button" wire:click="$refresh">{{ __('Apply') }}</x-ui.button>
                            <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.badge variant="success">{{ $completionRate }}% {{ __('email-ready') }}</x-ui.badge>
                            <x-ui.badge variant="warning">{{ number_format($priorityUsers) }} {{ __('need review') }}</x-ui.badge>
                            <x-ui.badge variant="primary">{{ number_format($localeCoverage) }} {{ __('locales') }}</x-ui.badge>
                            <x-ui.badge variant="neutral">{{ number_format($loadedUsers) }} {{ __('loaded') }}</x-ui.badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 xl:justify-end">
                            <span class="text-xs" style="color: var(--theme-muted-text-color);" x-text="selectedCount > 0 ? `${selectedCount} {{ __('selected') }}` : `{{ __('No selection') }}`"></span>
                            @if ($latestUser?->created_at)
                                <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Latest') }}: {{ $latestUser->created_at->format('Y-m-d H:i') }}</span>
                            @endif

                            <x-ui.dialog :title="__('Delete selected users?')" :description="__('This permanently removes every selected account except the one currently signed in. This action cannot be undone.')" width="sm" dismissible>
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="danger" x-bind:disabled="selectedCount === 0">
                                        {{ __('Delete selected') }}
                                    </x-ui.button>
                                </x-slot:trigger>

                                <x-slot:footer>
                                    <div class="flex justify-end gap-3">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button type="button" variant="danger" wire:click="deleteSelectedUsers">{{ __('Delete') }}</x-ui.button>
                                    </div>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </div>
                    </div>
                </div>
            </div>

                <div class="space-y-3 px-6 py-5 md:hidden">
                @forelse ($users as $user)
                        @php
                            $needsAttention = blank($user->email) || blank($user->role_id) || blank($user->plan_id);
                        @endphp
                    <x-ui.card padding="md" wire:key="user-mobile-{{ $user->id }}">
                            <div class="flex items-start gap-3">
                                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.95rem] border text-sm font-semibold uppercase" style="border-color: rgba(var(--theme-accent-rgb),0.14); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);">
                                    {{ $user->initials() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $user->name }}</p>
                                        <x-ui.badge :variant="$needsAttention ? 'danger' : 'success'">{{ $needsAttention ? __('Needs review') : __('Ready') }}</x-ui.badge>
                                    </div>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('ID') }} #{{ $user->id }} / {{ strtoupper($user->locale ?: 'UNSET') }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[0.9rem] border px-3 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Access') }}</p>
                                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $user->role?->name ?? __('No role assigned') }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$user->username }}</p>
                                </div>
                                <div class="rounded-[0.9rem] border px-3 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Plan') }}</p>
                                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $user->plan?->name ?? __('No plan') }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $user->plan_expires_at?->format('Y-m-d') ? __('Expires').' '.$user->plan_expires_at->format('Y-m-d') : __('No expiry set') }}</p>
                                </div>
                                <div class="rounded-[0.9rem] border px-3 py-3 sm:col-span-2" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Contact') }}</p>
                                    <p class="mt-2 text-sm font-medium break-all" style="color: var(--theme-header-text-color);">{{ $user->email ?: '-' }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $user->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <x-ui.dropdown-menu align="right" width="auto">
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="outline" size="sm">{{ __('Actions') }} <i class="fa-light fa-chevron-down text-[10px]"></i></x-ui.button>
                                    </x-slot:trigger>

                                    @if ($authUser?->canImpersonate() && $user->canBeImpersonatedBy($authUser))
                                        <x-ui.dropdown-menu-item class="w-full pr-6" icon="fa-light fa-user-secret" x-on:click.stop="open = false; document.getElementById('user-impersonate-trigger-{{ $user->id }}')?.click()">
                                            {{ __('View as user') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                    <x-ui.dropdown-menu-item href="{{ route('admin-users.edit', $user) }}" icon="fa-light fa-pen-to-square" class="w-full pr-6" wire:navigate>
                                        {{ __('Edit') }}
                                    </x-ui.dropdown-menu-item>

                                    <x-ui.dropdown-menu-divider />

                                    <x-ui.dropdown-menu-item class="w-full pr-6" icon="fa-light fa-trash-can" variant="danger" x-on:click.stop="open = false; document.getElementById('user-delete-trigger-{{ $user->id }}')?.click()">
                                        {{ __('Delete') }}
                                    </x-ui.dropdown-menu-item>
                                </x-ui.dropdown-menu>

                                @if ($authUser?->canImpersonate() && $user->canBeImpersonatedBy($authUser))
                                    <x-ui.dialog :title="__('View as this user?')" :description="__('You will temporarily browse the app using this account until you return to admin mode.')" width="sm" dismissible>
                                        <x-slot:trigger><button id="user-impersonate-trigger-{{ $user->id }}" type="button" class="hidden"></button></x-slot:trigger>
                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                <form method="POST" action="{{ route('admin-users.impersonate', $user) }}">
                                                    @csrf
                                                    <x-ui.button type="submit">{{ __('Continue') }}</x-ui.button>
                                                </form>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                @endif

                                <x-ui.dialog :title="__('Delete this user?')" :description="__('This permanently removes the account from the system. This action cannot be undone.')" width="sm" dismissible>
                                    <x-slot:trigger><button id="user-delete-trigger-{{ $user->id }}" type="button" class="hidden"></button></x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="deleteUser({{ $user->id }})">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.card>
                @empty
                    <div class="py-10 text-center" style="color: var(--theme-muted-text-color);">{{ __('No users found.') }}</div>
                @endforelse
            </div>

            <div class="hidden md:block">
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head class="w-12">
                            <x-ui.checkbox
                                id="user-bulk-select-all"
                                minimal
                                x-on:change="toggleAll($event)"
                            />
                        </x-ui.table-cell>
                        <x-ui.table-cell head>
                            <button type="button" wire:click="sortBy('user')" class="inline-flex items-center gap-2 transition hover:opacity-80">
                                <span>{{ __('User') }}</span>
                                <i class="{{ $sortIcon('user') }} text-[11px]"></i>
                            </button>
                        </x-ui.table-cell>
                        <x-ui.table-cell head>
                            <button type="button" wire:click="sortBy('access')" class="inline-flex items-center gap-2 transition hover:opacity-80">
                                <span>{{ __('Access') }}</span>
                                <i class="{{ $sortIcon('access') }} text-[11px]"></i>
                            </button>
                        </x-ui.table-cell>
                        <x-ui.table-cell head>
                            <button type="button" wire:click="sortBy('plan')" class="inline-flex items-center gap-2 transition hover:opacity-80">
                                <span>{{ __('Plan') }}</span>
                                <i class="{{ $sortIcon('plan') }} text-[11px]"></i>
                            </button>
                        </x-ui.table-cell>
                        <x-ui.table-cell head>
                            <button type="button" wire:click="sortBy('contact')" class="inline-flex items-center gap-2 transition hover:opacity-80">
                                <span>{{ __('Contact') }}</span>
                                <i class="{{ $sortIcon('contact') }} text-[11px]"></i>
                            </button>
                        </x-ui.table-cell>
                        <x-ui.table-cell head>
                            <button type="button" wire:click="sortBy('created')" class="inline-flex items-center gap-2 transition hover:opacity-80">
                                <span>{{ __('Created') }}</span>
                                <i class="{{ $sortIcon('created') }} text-[11px]"></i>
                            </button>
                        </x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
                    </x-ui.table-head>
                        <x-ui.table-body>
                            @forelse ($users as $user)
                                @php
                                    $needsAttention = blank($user->email) || blank($user->role_id) || blank($user->plan_id);
                                @endphp
                                <x-ui.table-row wire:key="user-row-{{ $user->id }}">
                                    <x-ui.table-cell>
                                        <x-ui.checkbox x-model="selectedIds" value="{{ (string) $user->id }}" minimal />
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="flex items-center gap-3">
                                            <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.9rem] border text-xs font-semibold uppercase" style="border-color: rgba(var(--theme-accent-rgb),0.14); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);">
                                                {{ $user->initials() }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $user->name }}</p>
                                                    <x-ui.badge :variant="$needsAttention ? 'danger' : 'success'">{{ $needsAttention ? __('Needs review') : __('Ready') }}</x-ui.badge>
                                                </div>
                                                <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('ID') }} #{{ $user->id }} / {{ strtoupper($user->locale ?: 'UNSET') }}</p>
                                            </div>
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="space-y-1">
                                            <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $user->role?->name ?? __('No role assigned') }}</p>
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$user->username }}</p>
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="space-y-1">
                                            <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $user->plan?->name ?? __('No plan') }}</p>
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $user->plan_expires_at?->format('Y-m-d') ? __('Expires').' '.$user->plan_expires_at->format('Y-m-d') : __('No expiry set') }}</p>
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="space-y-1">
                                            <p class="font-medium break-all" style="color: var(--theme-header-text-color);">{{ $user->email ?: '-' }}</p>
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ filled($user->email) ? __('Primary contact channel') : __('No email attached') }}</p>
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="space-y-1">
                                            <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $user->created_at?->format('Y-m-d') ?? '-' }}</p>
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $user->created_at?->diffForHumans() ?? '-' }}</p>
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        <div class="flex items-center justify-end pr-4">
                                            <x-ui.dropdown-menu align="right" width="auto">
                                                <x-slot:trigger>
                                                    <x-ui.button type="button" variant="outline" size="sm">{{ __('Actions') }} <i class="fa-light fa-chevron-down text-[10px]"></i></x-ui.button>
                                                </x-slot:trigger>

                                                @if ($authUser?->canImpersonate() && $user->canBeImpersonatedBy($authUser))
                                                    <x-ui.dropdown-menu-item class="w-full pr-6" icon="fa-light fa-user-secret" x-on:click.stop="open = false; document.getElementById('user-impersonate-trigger-{{ $user->id }}')?.click()">
                                                        {{ __('View as user') }}
                                                    </x-ui.dropdown-menu-item>
                                                @endif

                                                <x-ui.dropdown-menu-item href="{{ route('admin-users.edit', $user) }}" icon="fa-light fa-pen-to-square" class="w-full pr-6" wire:navigate>
                                                    {{ __('Edit') }}
                                                </x-ui.dropdown-menu-item>

                                                <x-ui.dropdown-menu-divider />

                                                <x-ui.dropdown-menu-item class="w-full pr-6" icon="fa-light fa-trash-can" variant="danger" x-on:click.stop="open = false; document.getElementById('user-delete-trigger-{{ $user->id }}')?.click()">
                                                    {{ __('Delete') }}
                                                </x-ui.dropdown-menu-item>
                                            </x-ui.dropdown-menu>
                                        </div>

                                        @if ($authUser?->canImpersonate() && $user->canBeImpersonatedBy($authUser))
                                            <x-ui.dialog :title="__('View as this user?')" :description="__('You will temporarily browse the app using this account until you return to admin mode.')" width="sm" dismissible>
                                                <x-slot:trigger><button id="user-impersonate-trigger-{{ $user->id }}" type="button" class="hidden"></button></x-slot:trigger>
                                                <x-slot:footer>
                                                    <div class="flex justify-end gap-3">
                                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                        <form method="POST" action="{{ route('admin-users.impersonate', $user) }}">
                                                            @csrf
                                                            <x-ui.button type="submit">{{ __('Continue') }}</x-ui.button>
                                                        </form>
                                                    </div>
                                                </x-slot:footer>
                                            </x-ui.dialog>
                                        @endif

                                        <x-ui.dialog :title="__('Delete this user?')" :description="__('This permanently removes the account from the system. This action cannot be undone.')" width="sm" dismissible>
                                            <x-slot:trigger><button id="user-delete-trigger-{{ $user->id }}" type="button" class="hidden"></button></x-slot:trigger>
                                            <x-slot:footer>
                                                <div class="flex justify-end gap-3">
                                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                    <x-ui.button type="button" variant="danger" wire:click="deleteUser({{ $user->id }})">{{ __('Delete') }}</x-ui.button>
                                                </div>
                                            </x-slot:footer>
                                        </x-ui.dialog>
                                    </x-ui.table-cell>
                                </x-ui.table-row>
                            @empty
                                <x-ui.table-row>
                                    <x-ui.table-cell colspan="7" class="py-10 text-center" style="color: var(--theme-muted-text-color);">{{ __('No users found.') }}</x-ui.table-cell>
                                </x-ui.table-row>
                            @endforelse
                        </x-ui.table-body>
                </x-ui.table>
            </div>

                @if ($users->hasPages())
                    <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">
                        {{ $users->links() }}
                    </div>
                @endif
        </x-ui.datatable-shell>
    </div>
</div>
