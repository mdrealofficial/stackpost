<div class="mx-auto max-w-[84rem] space-y-5">
    <x-ui.sub-header
        :eyebrow="__('Role management')"
        :title="$isEditing ? __('Edit user role') : __('Create user role')"
        :description="$isEditing ? __('Refine module access and action permissions for this role.') : __('Create a reusable permission group for backend users.')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-user-roles.index') }}" variant="outline" wire:navigate>{{ __('Back to roles') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.sub-header>

    <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
        <x-ui.card padding="none">
            <div class="border-b px-5 py-4" style="border-color: var(--theme-border-color);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Role form') }}</p>
                <h2 class="mt-2 text-[1.45rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                    {{ $isEditing ? __('Update role access') : __('Define a new role') }}
                </h2>
            </div>

            <form wire:submit="save" class="space-y-6 p-5">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="form.name" name="name" :label="__('Role name')" :error="$errors->first('form.name')" required autofocus />
                    <x-ui.input wire:model.defer="form.slug" name="slug" :label="__('Slug')" :error="$errors->first('form.slug')" :placeholder="__('auto-generated if empty')" />
                </div>

                <x-ui.textarea wire:model.defer="form.description" name="description" :label="__('Description')" :error="$errors->first('form.description')" rows="4" />

                <section class="space-y-4">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Module permissions') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Choose what this role can view, create, edit, delete, or export per module.') }}</p>
                    </div>

                    <div class="grid gap-4">
                        @foreach ($permissionGroups as $group)
                            <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $group['label'] }}</p>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($group['permissions'] as $permission)
                                        <div class="rounded-[0.8rem] border px-3 py-3" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                                            <x-ui.checkbox
                                                wire:model.defer="selectedPermissions"
                                                minimal
                                                name="permissions[]"
                                                value="{{ $permission['key'] }}"
                                                :label="$permission['label']"
                                                class="w-full"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-5" style="border-color: var(--theme-border-color);">
                    <div class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Roles become assignable immediately in the user form after saving.') }}</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button href="{{ route('admin-user-roles.index') }}" variant="outline" wire:navigate>{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit">{{ $isEditing ? __('Save changes') : __('Create role') }}</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-5">
            @if ($isEditing)
                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Assigned users') }}</p>
                    <p class="mt-3 text-[1.3rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ number_format($users->count()) }}</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($users->take(6) as $assignedUser)
                            <div class="rounded-[0.9rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $assignedUser->name }}</p>
                                <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$assignedUser->username }} · {{ $assignedUser->email }}</p>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('No users assigned to this role yet.') }}</p>
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Danger zone') }}</p>
                    <h3 class="mt-3 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Delete this role') }}</h3>
                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Users assigned to this role will be detached if you delete it.') }}</p>
                    <x-ui.dialog class="mt-5" width="sm" dismissible :title="__('Delete this role?')" :description="__('Deleting this role will detach it from users that currently have it assigned.')">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">{{ __('Delete role') }}</x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="deleteRole">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </x-ui.card>
            @else
                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('How to use') }}</p>
                    <div class="mt-4 grid gap-3">
                        @foreach ([
                            __('Create a role around a real responsibility such as Support Admin or Content Manager.'),
                            __('Grant only the module actions that role actually needs.'),
                            __('Assign the role from the user form after saving it.'),
                        ] as $note)
                            <div class="rounded-[0.9rem] border px-4 py-3 text-sm leading-7" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                {{ $note }}
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>
    </section>
</div>
