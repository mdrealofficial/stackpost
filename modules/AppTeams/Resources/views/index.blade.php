<div class="portal-tools teams-workspace space-y-5">
    @php
        $team = $teamModel;
    @endphp
    @include('partials.portal-tools-style')
    <style>
        .teams-tabs{display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--theme-border-color);padding-bottom:12px}
        .teams-tabs button{padding:9px 14px;border-radius:8px;font-size:13px;color:var(--theme-muted-text-color)}
        .teams-tabs button[aria-pressed="true"]{background:rgba(var(--theme-accent-rgb),.09);color:var(--theme-accent);font-weight:600}
        .teams-member{display:flex;gap:18px;align-items:center;justify-content:space-between;padding:18px 0;border-bottom:1px solid var(--theme-border-color)}
        .teams-member:last-child{border-bottom:0}.teams-muted{color:var(--theme-muted-text-color);font-size:12px;line-height:1.7}
        .teams-role{font-size:11px;border:1px solid var(--theme-border-color);padding:3px 8px;border-radius:6px;color:var(--theme-muted-text-color)}
        .teams-review{padding:20px;border:1px solid var(--theme-border-color);border-radius:12px;background:var(--theme-surface-base)}
        .teams-message{padding:12px 14px;background:var(--theme-surface-soft);border-radius:9px;margin-bottom:8px;overflow-wrap:anywhere}
        @media(max-width:640px){.teams-member{align-items:flex-start;flex-direction:column}.teams-member .portal-tool-actions{gap:6px}}
    </style>
    @if($joiningInvite)
        <div class="portal-tool-panel space-y-4">
            <h1 class="text-xl font-semibold">{{ __('Join a workspace') }}</h1>
            <p class="teams-muted">{{ __('Confirm to accept this invitation using your current signed-in account. Email invitations require the matching email address.') }}</p>
            <form wire:submit="redeemInvite" class="space-y-4">
                <x-ui.input wire:model="invite_code" name="invite_code" :label="__('Invitation code')" :error="$errors->first('invite_code')" />
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Accept invitation') }}</x-ui.button>
                <x-ui.button :href="route('portal.teams')" variant="outline">{{ __('Cancel') }}</x-ui.button>
            </form>
        </div>
    @else
    <x-ui.sub-header compact :title="$team?->name ?: __('Teams')" :description="__('Manage people, channel access and publishing approvals in one place.')"><x-slot:actions>@if($canManageInvites) @include('appteams::partials.invite') @endif</x-slot:actions></x-ui.sub-header>
    @if($errorMessage)<x-ui.alert inline variant="danger" :description="$errorMessage" />@endif
    @if($errors->any())<div role="alert" class="portal-tool-notice">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(!$team)
        <x-ui.empty :title="__('No workspace selected')" :description="__('Ask the workspace owner for an invitation.')" />
        <form wire:submit="redeemInvite" class="flex gap-3"><x-ui.input wire:model="invite_code" name="invite_code" :label="__('Invitation code')" /><x-ui.button type="submit">{{ __('Join workspace') }}</x-ui.button></form>
    @else
    @php
        $metricCards = [
            ['label' => __('Members'), 'value' => $memberCount, 'description' => __('People with access to this workspace.'), 'icon' => 'fa-light fa-users', 'tone' => 'rgb(37, 99, 235)', 'iconSurface' => 'rgba(37, 99, 235, 0.10)'],
            ['label' => __('Accessible channels'), 'value' => $workspaceAccounts->count(), 'description' => __('Channels available to this team.'), 'icon' => 'fa-light fa-share-nodes', 'tone' => 'rgb(5, 150, 105)', 'iconSurface' => 'rgba(5, 150, 105, 0.10)'],
        ];
        if ($canManageInvites) {
            $metricCards[] = ['label' => __('Active invitations'), 'value' => $inviteCount, 'description' => __('Invitations still waiting for a response.'), 'icon' => 'fa-light fa-envelope-open-text', 'tone' => 'rgb(217, 119, 6)', 'iconSurface' => 'rgba(217, 119, 6, 0.12)'];
        }
        if ($canApprovePosts) {
            $metricCards[] = ['label' => __('Awaiting your review'), 'value' => $pendingReviewCount, 'description' => __('Posts waiting for an approval decision.'), 'icon' => 'fa-light fa-clipboard-check', 'tone' => 'rgb(79, 70, 229)', 'iconSurface' => 'rgba(79, 70, 229, 0.10)'];
        }
    @endphp
    <x-ui.metric-strip :items="$metricCards" columns="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4" gap="gap-5" variant="portal" :show-progress="false" aria-label="{{ __('Team workspace summary') }}" />
    <nav class="teams-tabs" aria-label="{{ __('Team sections') }}">
        @foreach(['members'=>__('Members'),'invitations'=>__('Invitations'),'approvals'=>__('Approvals'),'overview'=>__('Settings'),'activity'=>__('Activity'),'chat'=>__('Team chat')] as $key=>$label)
            @if(in_array($key,$tabs,true))<button type="button" wire:click="selectTab('{{ $key }}')" aria-pressed="{{ $teams_tab === $key ? 'true':'false' }}">{{ $label }}</button>@endif
        @endforeach
    </nav>

    @if($teams_tab === 'members')
    <section class="portal-tool-panel">
        <div class="max-w-md"><x-ui.input wire:model.live.debounce.300ms="memberSearch" name="member_search" :label="__('Find a member')" :placeholder="__('Search name or email')" /></div>
        @forelse($members as $member)
            @php($isOwner=(int)$team->owner_user_id === (int)$member->id)
            @php($permissions=\Modules\AppTeams\Support\TeamWorkspaceAccess::permissionsForUser($member,$team))
            @php($assignedCount=count(\Modules\AppTeams\Support\TeamWorkspaceAccess::accessibleAccountIds($member,$team)))
            <div class="teams-member" wire:key="team-member-{{ $member->id }}">
                <div class="min-w-0"><div class="flex flex-wrap gap-2 items-center"><h2 class="font-semibold">{{ $member->name }}</h2><span class="teams-role">{{ $isOwner ? __('Owner') : __(['admin'=>'Admin','editor'=>'Editor','member'=>'Member'][$member->pivot->role] ?? 'Member') }}</span></div><p class="teams-muted break-all">{{ $member->email }}</p><p class="teams-muted">{{ $isOwner ? __('All channels · Full access') : __(':accounts channels assigned · :permissions permissions',['accounts'=>$assignedCount,'permissions'=>count($permissions)]) }}</p></div>
                @if(in_array($member->id,$editableMemberIds))
                <div class="portal-tool-actions">
                    <x-ui.modal width="lg" :title="__('Permissions for :name',['name'=>$member->name])" :description="__('Choose a preset or customize access. Unchecking every permission removes all optional access.')">
                        <x-slot:trigger><x-ui.button type="button" variant="outline" size="sm">{{ __('Permissions') }}</x-ui.button></x-slot:trigger>
                        <form id="permissions-{{ $member->id }}" wire:submit="updateMemberPermissions({{ $member->id }})" class="space-y-4">
                            <div class="flex gap-2 flex-wrap">@foreach(['viewer'=>__('Viewer'),'editor'=>__('Editor'),'reviewer'=>__('Reviewer')] as $key=>$label)<x-ui.button type="button" size="sm" variant="outline" wire:click="applyPermissionPreset('{{ $key }}',{{ $member->id }})">{{ $label }}</x-ui.button>@endforeach</div>
                            <div class="grid gap-3 sm:grid-cols-2">@foreach($teamPermissionLabels as $key=>$label)<x-ui.checkbox wire:model="memberPermissions.{{ $member->id }}" :value="$key" :label="$label" />@endforeach</div>
                        </form>
                        <x-slot:footer><x-ui.button type="submit" form="permissions-{{ $member->id }}" wire:loading.attr="disabled">{{ __('Save permissions') }}</x-ui.button></x-slot:footer>
                    </x-ui.modal>
                    <x-ui.modal width="lg" :title="__('Channels for :name',['name'=>$member->name])" :description="__('Only assigned channels are available to this member. No selection means no channel access.')">
                        <x-slot:trigger><x-ui.button type="button" variant="outline" size="sm">{{ __('Channels') }}</x-ui.button></x-slot:trigger>
                        <form id="accounts-{{ $member->id }}" wire:submit="updateMemberManagedAccounts({{ $member->id }})"><div class="grid max-h-96 overflow-y-auto gap-3 sm:grid-cols-2">@foreach($workspaceAccounts as $account)<x-ui.checkbox wire:model="memberManagedAccounts.{{ $member->id }}" :value="(string)$account->id" :label="$account->display_name.' · '.str($account->provider_key)->headline()" />@endforeach</div></form>
                        <x-slot:footer><x-ui.button type="submit" form="accounts-{{ $member->id }}" wire:loading.attr="disabled">{{ __('Save channel access') }}</x-ui.button></x-slot:footer>
                    </x-ui.modal>
                    <x-ui.button type="button" size="sm" variant="danger" wire:click="removeMember({{ $member->id }})" wire:confirm="{{ __('Remove this member from the workspace?') }}">{{ __('Remove') }}</x-ui.button>
                </div>
                @endif
            </div>
        @empty <p class="py-8 teams-muted">{{ __('No members match your search.') }}</p> @endforelse
        <div class="pt-4">{{ $members->links() }}</div>
    </section>
    @endif

    @if($teams_tab === 'invitations' && $canManageInvites)
    <section class="portal-tool-panel space-y-3">
        <p class="teams-muted">{{ __('Email invitations are restricted to their recipient. Code-only links can be accepted once by whoever receives them.') }}</p>
        @forelse($pendingInvites as $invite)
            @php($expired=$invite->expires_at?->isPast())
            <div class="teams-member" wire:key="invite-{{ $invite->id }}"><div class="min-w-0"><h2 class="font-semibold break-all">{{ $invite->email ?: __('Single-use invite link') }}</h2><p class="teams-muted">{{ $expired ? __('Expired') : __('Active') }} · {{ __('Expires') }} {{ $invite->expires_at?->format('d M Y H:i') ?: __('Never') }}</p>
            @if($invite->email)<p class="teams-muted">{{ match(data_get($invite->metadata,'delivery_status')) {'sent'=>__('Email handed to mail server'),'failed'=>__('Email failed — retry available'),'not_configured'=>__('Email not configured'),default=>__('Email has not been sent')} }}</p>@endif
            @if(!$expired)<a class="text-sm break-all" style="color:var(--theme-accent)" href="{{ route('portal.teams.join',['inviteCode'=>$invite->invite_code]) }}">{{ route('portal.teams.join',['inviteCode'=>$invite->invite_code]) }}</a>@endif</div>
            <div class="portal-tool-actions">@if(!$expired && $invite->email)<x-ui.button type="button" size="sm" variant="outline" wire:click="resendInvite({{ $invite->id }})" wire:loading.attr="disabled">{{ __('Retry email') }}</x-ui.button>@endif <x-ui.button type="button" size="sm" variant="outline" wire:click="deleteInvite({{ $invite->id }})">{{ __('Revoke') }}</x-ui.button></div></div>
        @empty <x-ui.empty :title="__('No pending invitations')" :description="__('Invite a teammate and assign the access they need.')" /> @endforelse
        {{ $pendingInvites->links() }}
    </section>
    @endif

    @if($teams_tab === 'approvals' && $canApprovePosts)
    <section class="space-y-4">
        <div class="max-w-xs"><x-ui.select wire:model.live="reviewFilter" name="review_filter" :label="__('Show reviews')"><option value="pending">{{ __('Pending approval') }}</option><option value="history">{{ __('Decision history') }}</option></x-ui.select></div>
        @forelse($reviews as $review)
        <article class="teams-review space-y-4" wire:key="review-{{ $review->id }}">
            <div class="flex justify-between gap-3"><div><h2 class="font-semibold">{{ $review->post?->account?->display_name ?: __('Channel unavailable') }}</h2><p class="teams-muted">{{ __('Submitted by') }} {{ $review->submitter?->name }} · {{ $review->submitted_at?->format('d M Y H:i') }}</p></div><span class="teams-role">{{ __(str($review->status)->headline()->toString()) }}</span></div>
            <p class="text-sm whitespace-pre-wrap break-words">{{ data_get($review->post?->data,'caption',data_get($review->post?->data,'title',__('Untitled post'))) }}</p>
            @include('appteams::partials.review-media')
            <p class="teams-muted">{{ __('Schedule') }}: {{ data_get($review->metadata,'schedule_mode') === 'immediately' ? __('Publish after approval') : ($review->post?->time_post ? \Carbon\Carbon::createFromTimestamp($review->post->time_post)->format('d M Y H:i T') : __('Not scheduled')) }}</p>
            @if($review->decision_note)<p class="portal-tool-notice">{{ $review->decision_note }}</p>@endif
            @if($review->status === 'pending')<x-ui.textarea wire:model="reviewNotes.{{ $review->id }}" name="review_note_{{ $review->id }}" :label="__('Decision note')" rows="2" /><div class="portal-tool-actions"><x-ui.button type="button" wire:click="approveReview({{ $review->id }})" wire:loading.attr="disabled">{{ __('Approve') }}</x-ui.button><x-ui.button type="button" variant="outline" wire:click="rejectReview({{ $review->id }})" wire:loading.attr="disabled">{{ __('Request changes') }}</x-ui.button></div>@else<p class="teams-muted">{{ __('Decided by') }} {{ $review->decider?->name }} · {{ $review->decided_at?->format('d M Y H:i') }}</p>@endif
        </article>
        @empty <x-ui.empty :title="$reviewFilter === 'history' ? __('No decisions yet') : __('No posts awaiting review')" :description="__('Only posts for channels you can access are shown.')" /> @endforelse
        {{ $reviews->links() }}
    </section>
    @endif

    @if($teams_tab === 'overview' && $canManageTeam)
    <section class="portal-tool-panel">
        <form wire:submit="saveTeam" class="space-y-5">
            <x-ui.input wire:model="team_name" name="team_name" :label="__('Team name')" />
            <x-ui.textarea wire:model="team_description" name="team_description" :label="__('Description')" rows="2" />
            <details><summary class="cursor-pointer font-semibold text-sm">{{ __('Workspace features') }}</summary><p class="teams-muted my-3">{{ __('Choose the features available to members. Individual permissions still apply.') }}</p><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($teamModuleLabels as $key=>$label)<x-ui.checkbox wire:model="enabled_modules" :value="$key" :label="$label" />@endforeach</div></details>
            <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Save settings') }}</x-ui.button>
        </form>
    </section>
    @endif

    @if($teams_tab === 'activity' && $canManageMembers)
    <section class="portal-tool-panel">@forelse($activity as $entry)<div class="py-3 border-b" style="border-color:var(--theme-border-color)"><p class="text-sm">{{ $entry->description }}</p><p class="teams-muted">{{ $entry->causer?->name ?: __('Former member') }} · {{ $entry->created_at?->format('d M Y H:i') }}</p></div>@empty<x-ui.empty :title="__('No activity recorded yet')" :description="__('New membership, permission and approval changes will appear here.')" />@endforelse {{ $activity->links() }}</section>
    @endif

    @if($teams_tab === 'chat' && $canUseChat)
        @include('appteams::partials/chat')
    @endif
    @endif
    @endif
</div>
