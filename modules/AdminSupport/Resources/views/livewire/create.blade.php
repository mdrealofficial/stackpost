<div class="mx-auto max-w-[88rem] space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Support')"
        :title="__('New Ticket')"
        :description="__('Open a support ticket on behalf of a user and assign its initial workflow state.')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-support.index') }}" variant="outline" wire:navigate>{{ __('View all tickets') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_380px]">
        <x-ui.card class="overflow-hidden" padding="none">
            <form wire:submit="save">
                <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color); background:
                    radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 26%),
                    linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 68%, transparent), var(--theme-surface-base));">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Intake') }}</p>
                    <h3 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Ticket details') }}</h3>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Choose a user, write the issue clearly, then assign its initial support routing and priority.') }}</p>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <section class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,1.05fr)_minmax(280px,0.95fr)]">
                            <div class="space-y-4">
                                <x-ui.field :label="__('User')" :error="$errors->first('uid')">
                                    <div class="space-y-3">
                                        <x-ui.input
                                            wire:model.blur="userSearch"
                                            name="user_search"
                                            :placeholder="__('Search by name, email, or username')"
                                            autocomplete="off"
                                        />

                                        @if ($selectedUser)
                                            <div class="flex items-start justify-between gap-3 rounded-[1rem] border px-4 py-3" style="border-color: color-mix(in srgb, var(--theme-accent) 16%, var(--theme-border-color)); background-color: color-mix(in srgb, var(--theme-accent) 6%, transparent);">
                                                <div class="min-w-0">
                                                    <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $selectedUser->name }}</p>
                                                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $selectedUser->email ?: '@'.$selectedUser->username }}</p>
                                                </div>
                                                <x-ui.button type="button" variant="outline" size="sm" wire:click="clearSelectedUser">{{ __('Change') }}</x-ui.button>
                                            </div>
                                        @elseif ($userSearch !== '')
                                            <div class="overflow-hidden rounded-[1rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                                                @forelse ($userResults as $user)
                                                    <button
                                                        type="button"
                                                        wire:click="selectUser({{ $user->id }})"
                                                        class="flex w-full items-start justify-between gap-3 border-b px-4 py-3 text-left transition hover:bg-black/5 dark:hover:bg-white/5"
                                                        style="border-color: var(--theme-border-color);"
                                                    >
                                                        <div class="min-w-0">
                                                            <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $user->name }}</p>
                                                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $user->email ?: '@'.$user->username }}</p>
                                                        </div>
                                                        <span class="shrink-0 text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Select') }}</span>
                                                    </button>
                                                @empty
                                                    <div class="px-4 py-3 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No users found for the current search.') }}</div>
                                                @endforelse
                                            </div>
                                        @endif
                                    </div>
                                </x-ui.field>

                                <x-ui.input wire:model.defer="title" name="title" :label="__('Subject')" :error="$errors->first('title')" :placeholder="__('Enter a short ticket title')" />
                            </div>

                            <div class="rounded-[1.35rem] border px-5 py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background:
                                linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 54%, transparent), transparent),
                                var(--theme-surface-base);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Routing') }}</p>
                                <div class="mt-4 grid gap-4">
                                    <x-ui.select wire:model.defer="cateId" name="cateId" :label="__('Category')" :error="$errors->first('cateId')">
                                        <option value="">{{ __('Unassigned') }}</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select wire:model.defer="typeId" name="typeId" :label="__('Type')" :error="$errors->first('typeId')">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($types as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                            </div>
                        </div>

                        <x-ui.textarea wire:model.defer="content" name="content" :label="__('Message')" :error="$errors->first('content')" rows="9" />
                    </section>

                    <section class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-[1.2rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 34%, transparent);">
                            <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                                <div class="mt-1 flex flex-wrap gap-x-5 gap-y-3">
                                    <x-ui.radio name="status" value="1" :label="__('Open')" wire:model.defer="status" />
                                    <x-ui.radio name="status" value="2" :label="__('Resolved')" wire:model.defer="status" />
                                    <x-ui.radio name="status" value="0" :label="__('Closed')" wire:model.defer="status" />
                                </div>
                            </x-ui.field>
                        </div>

                        <div class="rounded-[1.2rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 34%, transparent);">
                            <x-ui.field :label="__('Priority')" :error="$errors->first('pin')">
                                <div class="mt-1 flex flex-wrap gap-x-5 gap-y-3">
                                    <x-ui.radio name="pin" value="0" :label="__('Normal')" wire:model.defer="pin" />
                                    <x-ui.radio name="pin" value="1" :label="__('Pinned')" wire:model.defer="pin" />
                                </div>
                            </x-ui.field>
                        </div>
                    </section>

                    <section class="rounded-[1.2rem] border px-4 py-4" style="border-color: color-mix(in srgb, var(--theme-border-color) 94%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 34%, transparent);">
                        <x-ui.tag-selector
                            name="labelIds"
                            wire-model="labelIds"
                            :label="__('Labels')"
                            :options="$labels->map(fn ($label) => ['key' => (string) $label->id, 'label' => $label->name])->all()"
                            :selected="collect($labelIds)->map(fn ($id) => (string) $id)->all()"
                            :description="__('Search a label name to add it. Selected labels stay visible as removable tags.')"
                            :placeholder="__('Search labels...')"
                            :empty-label="__('No matching labels found.')"
                            :error="$errors->first('labelIds') ?: $errors->first('labelIds.*')"
                        />
                    </section>
                </div>

                <div class="flex justify-end gap-3 border-t px-6 py-5" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 34%, transparent);">
                    <x-ui.button href="{{ route('admin-support.index') }}" variant="outline" wire:navigate>{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit">{{ __('Create ticket') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card class="space-y-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Notes') }}</p>
                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Before you submit') }}</h3>
                <div class="space-y-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    <p>{{ __('Use this screen when support staff needs to open a ticket manually for a customer.') }}</p>
                    <p>{{ __('The ticket is created immediately and can be edited further from the ticket detail screen.') }}</p>
                    <p>{{ __('Categories, labels, and types are managed from the sibling support menu items.') }}</p>
                </div>
            </x-ui.card>

            <x-ui.card class="space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Checklist') }}</p>
                <div class="space-y-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    <p>{{ __('Pick the correct user first so ticket ownership is accurate from the start.') }}</p>
                    <p>{{ __('Use category and type to route the ticket for later review and reporting.') }}</p>
                    <p>{{ __('Add labels only when they help triage, not as a substitute for the message body.') }}</p>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
