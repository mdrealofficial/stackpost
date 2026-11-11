<div class="mx-auto max-w-[84rem] space-y-5">
    <x-ui.page-hero
        :eyebrow="__('Team administration')"
        :title="__('Edit team')"
        :description="__('Maintain auto-provisioned team details, ownership, and membership in one place.')"
        icon="fa-light fa-people-group"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-user-teams.index') }}" variant="outline" wire:navigate>{{ __('Back to teams') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="grid gap-5 xl:grid-cols-[1.12fr_0.88fr]">
        <x-ui.card padding="none">
            <div class="border-b px-5 py-4" style="border-color: var(--theme-border-color);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Team form') }}</p>
                <h2 class="mt-2 text-[1.45rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                    {{ __('Update team structure') }}
                </h2>
            </div>

            <form wire:submit="save" class="space-y-6 p-5">
                <x-ui.input wire:model.defer="form.name" name="name" :label="__('Team name')" :error="$errors->first('form.name')" required autofocus />

                <x-ui.textarea
                    wire:model.defer="form.description"
                    name="description"
                    :label="__('Description')"
                    :error="$errors->first('form.description')"
                    rows="5"
                    :placeholder="__('Describe the team scope, mission, or operating context.')"
                />

                <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-5" style="border-color: var(--theme-border-color);">
                    <div class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('The personal team owner is fixed and is automatically kept as the owner member.') }}</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button href="{{ route('admin-user-teams.index') }}" variant="outline" wire:navigate>{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-5">
            <x-ui.card>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Guidance') }}</p>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-[0.9rem] border px-4 py-3 text-sm leading-7" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                        <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('Owner') }}:</span>
                        {{ $team->owner?->name ?? __('Unknown owner') }}
                        @if($team->owner?->username)
                            <span>({{ '@'.$team->owner->username }})</span>
                        @endif
                    </div>
                    @foreach ([
                        __('Use short, stable team names because they appear throughout admin workflows.'),
                        __('Owners are provisioned automatically from the user who owns the personal team.'),
                        __('After saving, manage team members from this same page.'),
                    ] as $note)
                        <div class="rounded-[0.9rem] border px-4 py-3 text-sm leading-7" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                            {{ $note }}
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

        </div>
    </section>

    @if ($isEditing)
        <section class="grid gap-5 xl:grid-cols-[0.92fr_1.08fr]">
            <x-ui.card padding="none">
                <div class="border-b px-5 py-4" style="border-color: var(--theme-border-color);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Add member') }}</p>
                    <h3 class="mt-2 text-[1.3rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Attach a user to this team') }}</h3>
                </div>
                <form wire:submit="addMember" class="space-y-5 p-5">
                    <x-ui.select wire:model.defer="memberForm.user_id" name="user_id" :label="__('User')" :error="$errors->first('memberForm.user_id')">
                        <option value="">{{ __('Select a user') }}</option>
                        @foreach ($availableUsers as $memberUser)
                            <option value="{{ $memberUser->id }}">{{ $memberUser->name }} ({{ '@'.$memberUser->username }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select wire:model.defer="memberForm.role" name="role" :label="__('Role')" :error="$errors->first('memberForm.role')">
                        @foreach (['member' => __('Member'), 'lead' => __('Lead'), 'owner' => __('Owner')] as $roleValue => $roleLabel)
                            <option value="{{ $roleValue }}">{{ $roleLabel }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button type="submit" block>{{ __('Add member') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.datatable-shell :title="__('Members')" :info="__('Current users attached to this team.')">
                <x-slot:toolbar>
                    <x-ui.badge variant="primary">{{ number_format($team->members->count()) }} {{ __('members') }}</x-ui.badge>
                </x-slot:toolbar>

                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Role') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Email') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @forelse ($team->members as $member)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <div class="space-y-1">
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $member->name }}</p>
                                        <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$member->username }}</p>
                                    </div>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    @php
                                        $variant = $member->pivot->role === 'owner' ? 'primary' : ($member->pivot->role === 'lead' ? 'success' : 'neutral');
                                        $isOwnerMember = (int) $team->owner_user_id === (int) $member->id;
                                    @endphp
                                    <x-ui.badge :variant="$variant">{{ str($member->pivot->role)->headline() }}</x-ui.badge>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $member->email }}</x-ui.table-cell>
                                <x-ui.table-cell>
                                    @if ($isOwnerMember)
                                        <x-ui.badge variant="primary">{{ __('Owner locked') }}</x-ui.badge>
                                    @else
                                        <x-ui.dialog width="sm" dismissible :title="__('Remove this member?')" :description="__('This user will be detached from the team membership list.')">
                                            <x-slot:trigger>
                                                <x-ui.button type="button" variant="outline" size="sm">{{ __('Remove') }}</x-ui.button>
                                            </x-slot:trigger>
                                            <x-slot:footer>
                                                <div class="flex items-center justify-end gap-3">
                                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                    <x-ui.button type="button" variant="danger" wire:click="removeMember({{ $member->id }})">{{ __('Remove') }}</x-ui.button>
                                                </div>
                                            </x-slot:footer>
                                        </x-ui.dialog>
                                    @endif
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="4" class="py-10 text-center" style="color: var(--theme-muted-text-color);">{{ __('No team members yet.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            </x-ui.datatable-shell>
        </section>
    @endif
</div>
