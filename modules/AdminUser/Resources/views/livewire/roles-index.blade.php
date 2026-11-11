@php
    $roleCount = $roles->count();
    $assignedUsers = (int) $roles->sum('users_count');
    $permissionCount = (int) $roles->sum(fn ($role) => count($role->permissions ?? []));
    $metricCards = [
        ['label' => __('Roles'), 'value' => number_format($roleCount), 'description' => __('Available permission groups.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Assigned users'), 'value' => number_format($assignedUsers), 'description' => __('Users attached to a role.'), 'tone' => '#10b981', 'progress' => $assignedUsers > 0 ? 100 : 8],
        ['label' => __('Permissions'), 'value' => number_format($permissionCount), 'description' => __('Permission keys across roles.'), 'tone' => '#f59e0b', 'progress' => $permissionCount > 0 ? 100 : 8],
    ];
@endphp

<div class="mx-auto max-w-[84rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Access control')"
        :title="__('User Roles')"
        :description="__('Reusable permission groups for backend users, with clearer visibility into assignments and access coverage.')"
        :count="$roleCount"
        icon="fa-light fa-shield-keyhole"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-user-roles.create') }}" wire:navigate>{{ __('Create role') }}</x-ui.button>
            <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Open users') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-3" />

    <x-ui.datatable-shell :title="__('Role directory')" :info="__('Roles, assigned users, and permission coverage across backend modules.')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
        <x-slot:toolbar>
            <x-ui.badge variant="primary">{{ number_format($roleCount) }} {{ __('roles') }}</x-ui.badge>
        </x-slot:toolbar>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Role') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Users') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Permissions') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($roles as $role)
                    <x-ui.table-row>
                        <x-ui.table-cell><div class="space-y-1"><p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $role->name }}</p><p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $role->description ?: __('No description yet') }}</p></div></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge variant="success">{{ number_format($role->users_count) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge variant="primary">{{ number_format(count($role->permissions ?? [])) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center justify-end gap-2 pr-4">
                                <x-ui.button href="{{ route('admin-user-roles.edit', $role) }}" variant="outline" size="sm" wire:navigate>{{ __('Manage') }}</x-ui.button>
                                <x-ui.dialog width="sm" dismissible :title="__('Delete this role?')" :description="__('Deleting this role will detach it from users that currently have it assigned.')">
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                    </x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex items-center justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="deleteRole({{ $role->id }})">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="4" class="py-10"><x-ui.empty icon="fa-light fa-shield-halved" :title="__('No roles found.')" :description="__('Create the first role to start grouping backend permissions.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    </x-ui.datatable-shell>
</div>
