<x-ui.modal
                        width="lg"
                        open-event="team-invite-modal-open"
                        close-event="team-invite-modal-close"
                        :initially-open="$errors->hasAny(['email', 'message', 'expires_at', 'invite_permissions', 'inviteManagedAccounts'])"
                        :title="__('Invite member')"
                        :description="__('Enter an email to invite one person, or leave it blank to create a single-use link.')"
                        body-class="px-6 py-6"
                    >
                        <x-slot:trigger>
                            <x-ui.button type="button">
                                <i class="fa-light fa-user-plus mr-2"></i>{{ __('Invite member') }}
                            </x-ui.button>
                        </x-slot:trigger>

                        <form id="team-invite-modal-form" wire:submit="createInvite" class="space-y-5">
                            <x-ui.input wire:model="email" name="email" :label="__('Email address')" :placeholder="__('member@company.com')" :error="$errors->first('email')" />

                            <x-ui.textarea wire:model="message" name="message" :label="__('Invite message')" :error="$errors->first('message')" rows="3">{{ $message }}</x-ui.textarea>

                            <p class="text-sm" style="color:var(--theme-muted-text-color)">{{ __('An email invitation can only be accepted by that address. A link without an email can be used by anyone who receives it, once.') }}</p>
<div class="flex flex-wrap gap-2">@foreach(['viewer'=>__('Viewer'),'editor'=>__('Editor'),'reviewer'=>__('Reviewer')] as $key=>$label)<x-ui.button type="button" variant="outline" size="sm" wire:click="applyPermissionPreset('{{ $key }}')">{{ $label }}</x-ui.button>@endforeach</div>
<x-ui.field :label="__('Permissions')" :help="__('Choose what this invited member will be allowed to access after joining.')">
                                <div class="grid max-h-64 overflow-y-auto gap-3 md:grid-cols-2">
                                    @foreach ($teamPermissionLabels as $permissionKey => $permissionLabel)
                                        <x-ui.checkbox
                                            wire:model="invite_permissions"
                                            :value="$permissionKey"
                                            :label="$permissionLabel"
                                        />
                                    @endforeach
                                </div>
                                @if ($errors->first('invite_permissions'))
                                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('invite_permissions') }}</p>
                                @endif
                            </x-ui.field>

                            @if (in_array('channels', $enabled_modules, true) && $workspaceAccounts->isNotEmpty())
                                <x-ui.field :label="__('Managed accounts')" :help="__('Choose which channel accounts this invited member will be allowed to view and manage after joining.')">
                                    <div class="grid max-h-64 overflow-y-auto gap-3 md:grid-cols-2">
                                        @foreach ($workspaceAccounts as $workspaceAccount)
                                            <x-ui.checkbox
                                                wire:model.live="inviteManagedAccounts"
                                                data-no-loading
                                                :value="$workspaceAccount->id"
                                                :label="$workspaceAccount->display_name ?: (($workspaceAccount->username ? '@'.$workspaceAccount->username.' ' : '').str($workspaceAccount->provider_key)->headline())"
                                            />
                                        @endforeach
                                    </div>
                                    @if ($errors->first('inviteManagedAccounts'))
                                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('inviteManagedAccounts') }}</p>
                                    @endif
                                </x-ui.field>
                            @endif

                            <x-ui.date-picker wire:model="expires_at" name="expires_at" :label="__('Expires on')" :value="$expires_at" :error="$errors->first('expires_at')" />
                        </form>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="$dispatch('team-invite-modal-close')">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" form="team-invite-modal-form" wire:loading.attr="disabled">{{ __('Create invite') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.modal>