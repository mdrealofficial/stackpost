<?php

namespace Modules\AppTeams\Livewire;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminUser\Models\AuditLog;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Mail\TeamInvitationMail;
use Modules\AppTeams\Models\TeamConversation;
use Modules\AppTeams\Models\TeamInvitation;
use Modules\AppTeams\Models\TeamMessage;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Teams')]
class Workspace extends Component
{
    use WithPagination;

    public string $memberSearch = '';

    public string $reviewFilter = 'pending';

    #[Locked]
    public bool $joiningInvite = false;

    public function updatingMemberSearch(): void
    {
        $this->resetPage('membersPage');
    }

    public function updatedReviewFilter(): void
    {
        $this->resetPage('reviewsPage');
    }

    public function selectTab(string $tab): void
    {
        $allowed = $this->allowedTabs($this->currentTeamOrFail());
        abort_unless(in_array($tab, $allowed, true), 403);
        $this->teams_tab = $tab;
    }

    protected function allowedTabs(Team $team): array
    {
        $tabs = ['members'];
        if ($this->canManageInvites($team, $this->user()->id)) {
            $tabs[] = 'invitations';
        }
        if ($this->canApprovePosts($team, $this->user()->id)) {
            $tabs[] = 'approvals';
        }
        if ($this->canManageTeam($team, $this->user()->id)) {
            $tabs[] = 'overview';
        }
        if ($this->canManageMembers($team, $this->user()->id)) {
            $tabs[] = 'activity';
        }
        if ($this->canUseChat($team, $this->user()->id)) {
            $tabs[] = 'chat';
        }

        return $tabs;
    }

    public string $team = '';

    public string $conversation = '';

    public string $teams_tab = 'members';

    public string $invite_code = '';

    public string $team_name = '';

    public string $team_description = '';

    public string $email = '';

    public string $role = 'member';

    public string $message = '';

    public string $expires_at = '';

    /** @var array<int, string> */
    public array $enabled_modules = [];

    /** @var array<int, string> */
    public array $invite_permissions = [];

    /** @var array<int, string> */
    public array $inviteManagedAccounts = [];

    /** @var array<int, array<int, string>> */
    public array $memberPermissions = [];

    /** @var array<int, array<int, string>> */
    public array $memberManagedAccounts = [];

    public string $conversation_title = '';

    public string $conversation_description = '';

    public string $edit_conversation_title = '';

    public string $edit_conversation_description = '';

    public string $initial_message = '';

    public string $body = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    protected function queryString(): array
    {
        return [
            'team' => ['except' => ''],
            'conversation' => ['except' => ''],
            'teams_tab' => ['except' => 'members'],
        ];
    }

    public function mount(?string $inviteCode = null): void
    {
        $this->invite_code = strtoupper((string) request()->query('invite', ''));

        if ($inviteCode) {
            $this->invite_code = strtoupper($inviteCode);
            $this->joiningInvite = true;

            return;
        }

        abort_unless($this->teamsEnabled(), 404);

        $this->initializeWorkspace();
    }

    public function updatedTeam(): void
    {
        $this->syncTeamState();
    }

    public function updatedTeamsTab(string $value): void
    {
        if (! in_array($value, ['overview', 'members', 'invitations', 'chat', 'approvals', 'activity'], true)) {
            $this->teams_tab = 'members';
        }
    }

    public function switchTeam(): mixed
    {
        $this->syncTeamState();

        return $this->redirectRoute('portal.teams', [
            'team' => $this->team,
        ], navigate: true);
    }

    public function openConversation(int $conversationId): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->conversationQuery($team)->whereKey($conversationId)->exists(), 403);
        $this->resetPage('chatPage');
        $this->conversation = (string) $conversationId;
    }

    public function refreshConversation(): void
    {
        // Livewire polling re-renders the active chat thread so new messages appear automatically.
    }

    public function redeemInvite(): mixed
    {

        $validated = $this->validate([
            'invite_code' => ['required', 'string', 'size:8'],
        ]);

        return $this->acceptInvite($this->user(), $validated['invite_code']);
    }

    public function saveTeam(): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageTeam($team, $this->user()->id), 403);

        $availableModules = array_keys($this->availableTeamModules());

        $validated = $this->validate([
            'team_name' => ['required', 'string', 'max:255'],
            'team_description' => ['nullable', 'string', 'max:2000'],
            'enabled_modules' => ['nullable', 'array'],
        ]);

        $team->update([
            'name' => trim($validated['team_name']),
            'description' => trim((string) ($validated['team_description'] ?? '')) ?: null,
            'enabled_modules' => collect((array) ($validated['enabled_modules'] ?? []))
                ->filter(fn ($module) => is_scalar($module) && in_array((string) $module, $availableModules, true))
                ->map(fn ($module) => (string) $module)
                ->unique()
                ->values()
                ->all(),
        ]);

        $this->enabled_modules = TeamWorkspaceAccess::enabledModules($team->fresh());
        TeamWorkspaceAccess::clearCache();
        $this->recordActivity($team, 'settings.updated', __('Workspace settings updated.'));
        $this->notifySuccess(__('Team updated successfully.'));
    }

    public function createInvite(): void
    {
        $this->errorMessage = null;
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageInvites($team, $this->user()->id), 403);

        if ($this->hasReachedTeamMemberLimit($team)) {
            $this->notifyError(__('Your current plan has reached the team member limit.'));

            return;
        }

        $validated = $this->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'invite_permissions' => ['nullable', 'array'],
            'invite_permissions.*' => ['string', Rule::in(array_keys($this->availableTeamPermissions($team)))],
            'inviteManagedAccounts' => ['nullable', 'array'],
            'inviteManagedAccounts.*' => ['string', Rule::in(array_map('strval', $this->availableManagedAccountIds($team)))],
        ]);

        $inviteRole = 'member';
        $invitePermissions = collect((array) ($validated['invite_permissions'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $inviteManagedAccounts = collect((array) ($validated['inviteManagedAccounts'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $invitation = DB::transaction(function () use ($team, $validated, $inviteRole, $invitePermissions, $inviteManagedAccounts) {
            Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            abort_if($this->hasReachedTeamMemberLimit($team), 422, __('Your team member limit has been reached.'));
            if (filled($validated['email'] ?? null) && TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->where('email', $validated['email'])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
                throw ValidationException::withMessages(['email' => __('An active invitation already exists for this email. Use Retry email in Invitations.')]);
            }

            return TeamInvitation::query()->create([
                'team_id' => $team->id,
                'invited_by_user_id' => $this->user()->id,
                'email' => $validated['email'] ?: null,
                'invite_code' => strtoupper(Str::random(8)),
                'role' => $inviteRole,
                'permissions' => $invitePermissions,
                'status' => 'pending',
                'message' => trim((string) ($validated['message'] ?? '')),
                'expires_at' => ! empty($validated['expires_at']) ? $validated['expires_at'] : now()->addDays(7),
                'metadata' => [
                    'delivery' => filled($validated['email'] ?? null) ? 'email' : 'code',
                    'managed_account_ids' => $inviteManagedAccounts,
                ],
            ]);

        }, 3);
        $this->recordActivity($team, 'invite.created', __('Invitation created.'));
        $this->deliverInvite($invitation);

        $this->reset('email', 'message', 'expires_at', 'inviteManagedAccounts');
        $this->invite_permissions = $this->defaultPermissionsForRole($inviteRole, $team);
        $this->role = 'member';
        if (! $this->errorMessage) {
            $this->notifySuccess(__('Invitation created. Check its delivery status in Invitations.'));
        }
        $this->teams_tab = 'invitations';
        $this->dispatch('team-invite-modal-close');
    }

    public function deleteInvite(int $inviteId): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageInvites($team, $this->user()->id), 403);

        $invite = TeamInvitation::query()
            ->whereKey($inviteId)
            ->where('team_id', $team->id)
            ->where('status', 'pending')
            ->first();

        if (! $invite) {
            $this->notifyError(__('This invite could not be found or has already been removed.'));

            return;
        }

        $invite->update(['status' => 'revoked']);
        $this->recordActivity($team, 'invite.revoked', __('Invitation revoked.'));

        $this->notifySuccess(__('Invite deleted successfully.'));
    }

    public function updateMemberPermissions(int $userId): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageMembers($team, $this->user()->id), 403);
        $this->assertManageTarget($team, $userId);

        $validated = $this->validate([
            "memberPermissions.$userId" => ['nullable', 'array'],
            "memberPermissions.$userId.*" => ['string', Rule::in(array_keys($this->availableTeamPermissions($team)))],
        ]);

        $permissions = collect((array) ($validated['memberPermissions'][$userId] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_unless($this->isTeamOwner($team, $this->user()->id) || array_diff($permissions, TeamWorkspaceAccess::permissionsForUser($this->user(), $team)) === [], 403);
        $team->members()->updateExistingPivot($userId, [
            'permissions' => $this->encodePermissions($permissions),
        ]);

        $this->memberPermissions[$userId] = $permissions;
        TeamWorkspaceAccess::clearCache();
        $this->recordActivity($team, 'member.updated', __('Member access changed.'), ['member_id' => $userId]);
        $this->notifySuccess(__('Member permissions updated successfully.'));
    }

    public function updateMemberManagedAccounts(int $userId): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageMembers($team, $this->user()->id), 403);
        $this->assertManageTarget($team, $userId);
        abort_unless(TeamWorkspaceAccess::teamHasModule($team, 'channels'), 403);

        $availableAccountIds = $this->availableManagedAccountIds($team);
        $availableAccountValues = array_map('strval', $availableAccountIds);

        $validated = $this->validate([
            "memberManagedAccounts.$userId" => ['nullable', 'array'],
            "memberManagedAccounts.$userId.*" => ['string', Rule::in($availableAccountValues)],
        ]);

        $managedAccountIds = collect((array) ($validated['memberManagedAccounts'][$userId] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // A delegated member manager can only pass along channels they can already access.
        // Owners retain full control over the workspace channel roster.
        abort_unless(
            $this->isTeamOwner($team, $this->user()->id)
            || array_diff($managedAccountIds, TeamWorkspaceAccess::accessibleAccountIds($this->user(), $team)) === [],
            403
        );

        $team->members()->updateExistingPivot($userId, [
            'managed_account_ids' => $this->encodeManagedAccountIds($managedAccountIds),
        ]);

        $this->memberManagedAccounts[$userId] = array_map('strval', $managedAccountIds);
        TeamWorkspaceAccess::clearCache();
        $this->recordActivity($team, 'member.updated', __('Member access changed.'), ['member_id' => $userId]);
        $this->notifySuccess(__('Member account access updated successfully.'));
    }

    public function removeMember(int $userId): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageMembers($team, $this->user()->id), 403);
        $this->assertManageTarget($team, $userId);

        if ($team->owner_user_id === $userId) {
            $this->notifyError(__('The team owner cannot be removed. Transfer ownership first.'));

            return;
        }

        DB::transaction(function () use ($team, $userId) {
            $team->members()->detach($userId);
            foreach (TeamConversation::where('team_id', $team->id)->get() as $room) {
                $room->participants()->detach($userId);
            }
        });
        unset($this->memberPermissions[$userId]);
        TeamWorkspaceAccess::clearCache();
        $this->recordActivity($team, 'member.updated', __('Member access changed.'), ['member_id' => $userId]);
        $this->notifySuccess(__('Member removed successfully.'));
    }

    public function storeConversation(): mixed
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageChat($team, $this->user()->id), 403);

        $validated = $this->validate([
            'conversation_title' => ['required', 'string', 'max:255'],
            'conversation_description' => ['nullable', 'string', 'max:1000'],
            'initial_message' => ['nullable', 'string', 'max:5000'],
        ]);

        $conversation = TeamConversation::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $this->user()->id,
            'type' => 'room',
            'title' => trim($validated['conversation_title']),
            'description' => trim((string) ($validated['conversation_description'] ?? '')) ?: null,
            'last_message_at' => null,
        ]);

        $participantIds = $team->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if ((int) $team->owner_user_id > 0) {
            $participantIds[] = (int) $team->owner_user_id;
        }

        $conversation->participants()->syncWithoutDetaching(
            collect($participantIds)
                ->unique()
                ->mapWithKeys(fn ($id) => [$id => ['role' => $id === (int) $team->owner_user_id ? 'owner' : 'member']])
                ->all()
        );

        $initialMessage = trim((string) ($validated['initial_message'] ?? ''));
        if ($initialMessage !== '') {
            TeamMessage::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->user()->id,
                'body' => $initialMessage,
                'sent_at' => now(),
            ]);

            $conversation->update(['last_message_at' => now()]);
        }

        $this->reset('conversation_title', 'conversation_description', 'initial_message');
        $this->conversation = (string) $conversation->id;
        $this->notifySuccess(__('Conversation created successfully.'));
        $this->dispatch('team-conversation-create-modal-close');

        return $this->redirectRoute('portal.teams', [
            'team' => $team->id,
            'conversation' => $conversation->id,
            'teams_tab' => 'chat',
        ], navigate: true);
    }

    public function storeMessage(?string $body = null): void
    {
        $team = $this->currentTeamOrFail();
        $conversation = $this->activeConversationModel($team);

        abort_if(! $conversation, 404);
        abort_unless($this->canUseChat($team, $this->user()->id), 403);
        abort_unless($this->canAccessConversation($conversation, $this->user()->id), 403);

        if ($body !== null) {
            $this->body = $body;
        }

        $validated = $this->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        TeamMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user()->id,
            'body' => trim($validated['body']),
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->reset('body');
        $this->resetPage('chatPage');
        $this->notifySuccess(__('Message sent successfully.'));
        $this->dispatch('team-chat-scroll-bottom');
    }

    public function beginEditConversation(): void
    {
        $team = $this->currentTeamOrFail();
        $conversation = $this->activeConversationModel($team);

        abort_if(! $conversation, 404);
        abort_unless($this->canManageChat($team, $this->user()->id), 403);

        $this->edit_conversation_title = (string) ($conversation->title ?? '');
        $this->edit_conversation_description = (string) ($conversation->description ?? '');

        $this->dispatch('team-conversation-edit-modal-open');
    }

    public function updateConversation(): void
    {
        $team = $this->currentTeamOrFail();
        $conversation = $this->activeConversationModel($team);

        abort_if(! $conversation, 404);
        abort_unless($this->canManageChat($team, $this->user()->id), 403);

        $validated = $this->validate([
            'edit_conversation_title' => ['required', 'string', 'max:255'],
            'edit_conversation_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $conversation->update([
            'title' => trim($validated['edit_conversation_title']),
            'description' => trim((string) ($validated['edit_conversation_description'] ?? '')) ?: null,
        ]);

        $this->notifySuccess(__('Conversation updated successfully.'));
        $this->dispatch('team-conversation-edit-modal-close');
    }

    public function deleteConversation(): void
    {
        $team = $this->currentTeamOrFail();
        $conversation = $this->activeConversationModel($team);

        abort_if(! $conversation, 404);
        abort_unless($this->canManageChat($team, $this->user()->id), 403);

        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->participants()->detach();
            $conversation->delete();
        });

        $this->conversation = (string) (TeamConversation::query()
            ->where('team_id', $team->id)
            ->latest('last_message_at')
            ->value('id') ?? '');

        $this->notifySuccess(__('Conversation deleted successfully.'));
    }

    public function approveReview(int $reviewId): void
    {
        $this->decideReview($reviewId, true);
    }

    public function rejectReview(int $reviewId): void
    {
        $this->decideReview($reviewId, false);
    }

    public function render(): View
    {
        abort_unless($this->joiningInvite || $this->teamsEnabled(), 404);
        $user = $this->user();
        $accessibleTeams = $this->accessibleTeams($user->id);
        $team = $this->currentTeamModel($accessibleTeams);
        $tabs = $team ? $this->allowedTabs($team) : [];
        if (! in_array($this->teams_tab, $tabs, true)) {
            $this->teams_tab = 'members';
        }
        $members = null;
        $pendingInvites = collect();
        $reviews = null;
        $conversations = collect();
        $activeConversation = null;
        $chatMessages = null;
        $activity = null;
        $pendingReviewCount = 0;
        $inviteCount = 0;
        $memberCount = 0;
        if ($team) {
            $team->loadMissing('owner', 'members');
            $memberCount = $team->members->count();
            $members = $team->members()->when($this->memberSearch !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->memberSearch.'%')->orWhere('email', 'like', '%'.$this->memberSearch.'%')))->orderBy('name')->paginate(20, ['users.*'], 'membersPage');
            if ($this->canManageInvites($team, $user->id)) {
                $pendingInvites = TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->latest()->paginate(15, ['*'], 'invitesPage');
                $inviteCount = TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
            }
            if ($this->canApprovePosts($team, $user->id)) {
                $query = TeamPostReview::where('team_id', $team->id)->whereHas('post', fn ($q) => $q->whereIn('account_id', TeamWorkspaceAccess::accessibleAccountIds($user, $team)));
                $pendingReviewCount = (clone $query)->where('status', 'pending')->count();
                $reviews = $query->when($this->reviewFilter === 'history', fn ($q) => $q->whereIn('status', ['approved', 'rejected']), fn ($q) => $q->where('status', 'pending'))->with(['post.account', 'submitter', 'decider'])->latest('submitted_at')->paginate(12, ['*'], 'reviewsPage');
            }
            if ($this->canUseChat($team, $user->id) && $this->teams_tab === 'chat') {
                $conversations = $this->conversationQuery($team)->withCount('messages')->latest('last_message_at')->get();
                $activeConversation = $this->activeConversationModel($team);
                if ($activeConversation) {
                    $chatMessages = $activeConversation->messages()->with('author')->latest('id')->paginate(40, ['*'], 'chatPage');
                }
            }
            if ($this->canManageMembers($team, $user->id) && $this->teams_tab === 'activity') {
                $activity = AuditLog::where('subject_type', Team::class)->where('subject_id', $team->id)->where('event', 'like', 'team.%')->with('causer')->latest()->paginate(20, ['*'], 'activityPage');
            }
        }

        return view('appteams::index', compact('team', 'members', 'pendingInvites', 'reviews', 'conversations', 'activeConversation', 'chatMessages', 'activity', 'tabs', 'pendingReviewCount', 'inviteCount', 'memberCount', 'accessibleTeams') + [
            'teamModel' => $team, 'user' => $user,
            'canManageTeam' => $this->canManageTeam($team, $user->id), 'canManageMembers' => $this->canManageMembers($team, $user->id),
            'canManageInvites' => $this->canManageInvites($team, $user->id), 'canApprovePosts' => $this->canApprovePosts($team, $user->id),
            'canManageChat' => $this->canManageChat($team, $user->id), 'canUseChat' => $this->canUseChat($team, $user->id),
            'teamPermissionLabels' => $this->availableTeamPermissions($team), 'teamModuleLabels' => $this->availableTeamModules(),
            'workspaceAccounts' => $team ? SocialAccount::whereIn('id', TeamWorkspaceAccess::accessibleAccountIds($user, $team))->orderBy('display_name')->get() : collect(),
            'editableMemberIds' => $team ? $team->members->filter(fn ($m) => $this->mayManageTarget($team, $m->id))->pluck('id')->all() : [],
        ])->layout(theme_view('layouts.app', 'app'), ['title' => __('Teams')]);
    }

    protected function initializeWorkspace(): void
    {
        $accessibleTeams = $this->accessibleTeams($this->user()->id);
        $this->syncTeamState(null, $accessibleTeams);
    }

    protected function syncTeamState(?Team $team = null, ?Collection $accessibleTeams = null): void
    {
        $accessibleTeams ??= $this->accessibleTeams($this->user()->id);
        $team ??= $this->currentTeamModel($accessibleTeams);

        if (! $team) {
            $this->team = '';
            $this->conversation = '';
            $this->team_name = '';
            $this->team_description = '';
            $this->enabled_modules = [];

            return;
        }

        $this->team = (string) $team->id;
        $this->team_name = (string) $team->name;
        $this->team_description = (string) ($team->description ?? '');
        $this->enabled_modules = TeamWorkspaceAccess::enabledModules($team);
        $this->invite_permissions = $this->defaultPermissionsForRole('member', $team);
        $this->inviteManagedAccounts = [];
        $this->syncMemberAccessState($team);
        session(['portal_team_id' => $team->id]);

        $conversationExists = TeamConversation::query()
            ->where('team_id', $team->id)
            ->when($this->conversation !== '', fn ($query) => $query->whereKey((int) $this->conversation))
            ->exists();

        if (! $conversationExists) {
            $this->conversation = (string) (TeamConversation::query()
                ->where('team_id', $team->id)
                ->latest('last_message_at')
                ->value('id') ?? '');
        }
    }

    protected function currentTeamOrFail(): Team
    {
        $team = $this->currentTeamModel();
        abort_if(! $team, 404);

        return $team;
    }

    protected function currentTeamModel(?Collection $accessibleTeams = null): ?Team
    {
        $accessibleTeams ??= $this->accessibleTeams($this->user()->id);

        if ($accessibleTeams->isEmpty()) {
            return null;
        }

        $requestedTeamId = (int) ($this->team !== '' ? $this->team : session('portal_team_id', 0));

        if ($requestedTeamId > 0) {
            $team = $accessibleTeams->firstWhere('id', $requestedTeamId);
            if ($team) {
                return $team;
            }
        }

        return $accessibleTeams->first();
    }

    protected function activeConversationModel(Team $team): ?TeamConversation
    {
        return $this->conversationQuery($team)
            ->when($this->conversation !== '', fn ($q) => $q->whereKey((int) $this->conversation))
            ->latest('last_message_at')->first();
    }

    protected function accessibleTeams(?int $userId): Collection
    {
        if (! $userId) {
            return collect();
        }

        return Team::query()
            ->where(function ($query) use ($userId) {
                $query->where('owner_user_id', $userId)
                    ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', $userId));
            })
            ->orderByRaw('CASE WHEN owner_user_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    protected function syncMemberAccessState(Team $team): void
    {
        $team->loadMissing([
            'members' => fn ($query) => $query->orderBy('name'),
        ]);

        $this->memberPermissions = $team->members
            ->mapWithKeys(fn ($member) => [(int) $member->id => (int) $team->owner_user_id === (int) $member->id
                ? $this->defaultPermissionsForRole('owner', $team)
                : $this->decodePermissions($member->pivot->permissions ?? null, (string) ($member->pivot->role ?? 'member'), $team)])
            ->all();

        $this->memberManagedAccounts = $team->members
            ->mapWithKeys(fn ($member) => [(int) $member->id => (int) $team->owner_user_id === (int) $member->id
                ? array_map('strval', $this->availableManagedAccountIds($team))
                : $this->decodeManagedAccountIds($member->pivot->managed_account_ids ?? null)])
            ->all();
    }

    protected function acceptInvite(User $user, string $inviteCode): mixed
    {
        $code = strtoupper(trim($inviteCode));
        $lookup = TeamInvitation::where('invite_code', $code)->first();
        if (! $lookup) {
            $this->addError('invite_code', __('This invitation is invalid or no longer available.'));

            return null;
        }
        $accepted = DB::transaction(function () use ($user, $lookup) {
            $team = Team::whereKey($lookup->team_id)->lockForUpdate()->firstOrFail();
            $invite = TeamInvitation::whereKey($lookup->id)->lockForUpdate()->firstOrFail();
            if ($invite->status !== 'pending' || ($invite->expires_at && $invite->expires_at->isPast())) {
                $this->addError('invite_code', __('This invitation has expired or already been used.'));

                return null;
            }
            if (filled($invite->email) && strcasecmp(trim($invite->email), trim($user->email)) !== 0) {
                $this->addError('invite_code', __('Sign in with the email address this invitation was sent to.'));

                return null;
            }
            if (! $team->members()->where('users.id', $user->id)->exists()) {
                if (! $this->canJoinTeamUnderCurrentPlan($user, $team)) {
                    $this->addError('invite_code', __('This team has reached its member limit.'));

                    return null;
                }
                $role = in_array($invite->role, ['admin', 'editor', 'member'], true) ? $invite->role : 'member';
                $team->members()->attach($user->id, [
                    'role' => $role, 'permissions' => $this->encodePermissions($this->decodePermissions($invite->permissions, $role, $team)),
                    'managed_account_ids' => $this->encodeManagedAccountIds(array_values(array_intersect(array_map('intval', (array) data_get($invite->metadata, 'managed_account_ids', [])), SocialAccount::where('created_by_user_id', $team->owner_user_id)->pluck('id')->all()))),
                ]);
                // Rooms created by this UI are shared team rooms, not private conversations.
                foreach (TeamConversation::where('team_id', $team->id)->where('type', 'room')->get() as $room) {
                    $room->participants()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
                }
            }
            $invite->update(['status' => 'accepted', 'accepted_by_user_id' => $user->id, 'accepted_at' => now()]);
            $this->recordActivity($team, 'member.joined', __('Member joined the workspace.'));

            return $team->id;
        }, 3);
        if (! $accepted) {
            return null;
        }
        TeamWorkspaceAccess::clearCache();
        session(['portal_team_id' => $accepted]);

        return $this->redirectRoute('portal.teams', ['team' => $accepted], navigate: true);
    }

    protected function canManageTeam(?Team $team, ?int $userId): bool
    {
        return $this->hasTeamPermission($team, $userId, 'team.manage');
    }

    protected function canManageMembers(?Team $team, ?int $userId): bool
    {
        return $this->hasTeamPermission($team, $userId, 'member.manage');
    }

    protected function canManageInvites(?Team $team, ?int $userId): bool
    {
        return $this->isTeamOwner($team, $userId);
    }

    protected function canApprovePosts(?Team $team, ?int $userId): bool
    {
        return $this->hasTeamPermission($team, $userId, 'post.approve');
    }

    protected function canManageChat(?Team $team, ?int $userId): bool
    {
        return $this->hasTeamPermission($team, $userId, 'chat.manage');
    }

    protected function canUseChat(?Team $team, ?int $userId): bool
    {
        if (! $team || ! $userId) {
            return false;
        }

        if (! TeamWorkspaceAccess::teamHasModule($team, 'chat')) {
            return false;
        }

        return $this->hasTeamPermission($team, $userId, 'chat.manage')
            || $this->hasTeamPermission($team, $userId, 'chat.participate');
    }

    protected function canAccessConversation(TeamConversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('users.id', $userId)->exists()
            || $conversation->team()->where('owner_user_id', $userId)->exists();
    }

    protected function defaultPermissionsForRole(string $role, ?Team $team = null): array
    {
        if ($role === 'owner') {
            return array_keys($this->availableTeamPermissions($team));
        }

        return TeamPermissionRegistry::defaultPermissionsForRole($role, TeamWorkspaceAccess::enabledModules($team));
    }

    protected function availableTeamPermissions(?Team $team = null): array
    {
        return TeamPermissionRegistry::permissionLabels(TeamWorkspaceAccess::enabledModules($team));
    }

    protected function availableTeamModules(): array
    {
        return TeamPermissionRegistry::moduleLabels(true);
    }

    protected function conversationTopicOptions(): array
    {
        return [
            __('Content planning') => __('Content planning'),
            __('Campaign coordination') => __('Campaign coordination'),
            __('Post approvals') => __('Post approvals'),
            __('Publishing schedule') => __('Publishing schedule'),
            __('Customer support') => __('Customer support'),
            __('General discussion') => __('General discussion'),
        ];
    }

    protected function hasTeamPermission(?Team $team, ?int $userId, string $permission): bool
    {
        if (! $team || ! $userId) {
            return false;
        }

        $user = User::query()->find($userId);

        return TeamWorkspaceAccess::hasPermission($user, $permission, $team);
    }

    protected function permissionsForTeamMember(Team $team, int $userId): array
    {
        $user = User::query()->find($userId);

        return TeamWorkspaceAccess::permissionsForUser($user, $team);
    }

    protected function currentUserRole(Team $team, int $userId): string
    {
        if ((int) $team->owner_user_id === $userId) {
            return 'owner';
        }

        return (string) ($team->members->firstWhere('id', $userId)?->pivot->role
            ?? $team->members()->where('users.id', $userId)->first()?->pivot?->role
            ?? 'member');
    }

    protected function isTeamOwner(?Team $team, ?int $userId): bool
    {
        return $team && $userId && (int) $team->owner_user_id === (int) $userId;
    }

    protected function decodePermissions(mixed $permissions, string $role = 'member', ?Team $team = null): array
    {
        if ($permissions === null) {
            return $this->defaultPermissionsForRole($role, $team);
        }
        $decoded = is_array($permissions) ? $permissions : json_decode((string) $permissions, true);

        return $this->filterPermissionsForEnabledModules(is_array($decoded) ? $decoded : [], $team);
    }

    protected function filterPermissionsForEnabledModules(array $permissions, ?Team $team): array
    {
        return collect($permissions)
            ->filter(fn ($permission) => $this->permissionEnabledForTeam((string) $permission, $team))
            ->map(fn ($permission) => (string) $permission)
            ->unique()
            ->values()
            ->all();
    }

    protected function permissionEnabledForTeam(string $permission, ?Team $team): bool
    {
        $module = TeamPermissionRegistry::permissionModule($permission);

        return $module ? TeamWorkspaceAccess::teamHasModule($team, $module) : true;
    }

    protected function encodePermissions(?array $permissions): ?string
    {
        if ($permissions === null) {
            return null;
        }

        return json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function decodeManagedAccountIds(mixed $managedAccountIds): array
    {
        if (is_array($managedAccountIds)) {
            return collect($managedAccountIds)
                ->map(fn ($id) => (string) (int) $id)
                ->filter(fn ($id) => (int) $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (is_string($managedAccountIds) && trim($managedAccountIds) !== '') {
            $decoded = json_decode($managedAccountIds, true);

            if (is_array($decoded)) {
                return collect($decoded)
                    ->map(fn ($id) => (string) (int) $id)
                    ->filter(fn ($id) => (int) $id > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    protected function encodeManagedAccountIds(?array $managedAccountIds): ?string
    {
        if ($managedAccountIds === null) {
            return null;
        }

        return json_encode(array_values($managedAccountIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function filterManagedAccountIdsForTeam(array $managedAccountIds, Team $team): array
    {
        $availableAccountIds = $this->availableManagedAccountIds($team);

        return collect($managedAccountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && in_array($id, $availableAccountIds, true))
            ->unique()
            ->values()
            ->all();
    }

    protected function availableManagedAccountIds(Team $team): array
    {
        return SocialAccount::query()
            ->whereIn('id', TeamWorkspaceAccess::accessibleAccountIds($this->user(), $team))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function makeQrCodeSvg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                220,
                2,
                null,
                null,
                Fill::uniformColor(new Rgb(15, 23, 42), new Rgb(255, 255, 255))
            ),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($value);
    }

    protected function notifySuccess(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
        $this->dispatch('app-toast', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->errorMessage = $message;
        $this->statusMessage = null;
        $this->dispatch('app-toast', type: 'error', message: $message);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function teamsEnabled(?Team $team = null): bool
    {
        $planUser = $this->teamsPlanUser($team);

        if (! $planUser?->plan) {
            return true;
        }

        return $planUser->hasPlanFeature('teams') || $planUser->hasPlanFeature('teams_feature');
    }

    protected function maxTeamMembers(?Team $team = null): ?int
    {
        $limit = (int) ($this->teamsPlanUser($team)?->planLimit('max_team_members', -1) ?? -1);

        return $limit < 0 ? null : $limit;
    }

    protected function hasReachedTeamMemberLimit(Team $team): bool
    {
        $limit = $this->maxTeamMembers($team);

        if ($limit === null) {
            return false;
        }

        return $team->members()->count() + TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count() >= $limit;
    }

    protected function canJoinTeamUnderCurrentPlan(User $user, Team $team): bool
    {
        $planUser = $this->teamsPlanUser($team) ?: $user;

        if ($planUser?->plan && ! ($planUser->hasPlanFeature('teams') || $planUser->hasPlanFeature('teams_feature'))) {
            return false;
        }

        $limit = (int) ($planUser?->planLimit('max_team_members', -1) ?? -1);

        if ($limit < 0) {
            return true;
        }

        return $team->members()->count() < $limit;
    }

    protected function teamsPlanUser(?Team $team = null): ?User
    {
        $user = auth()->user();
        $team ??= $this->currentTeamModel($user ? $this->accessibleTeams($user->id) : collect());
        $team ??= TeamWorkspaceAccess::activeTeam($user);

        return $team?->owner ?: $user;
    }

    protected function recordActivity(Team $team, string $event, string $description, array $metadata = []): void
    {
        log_activity('team.'.$event, $description, ['subject' => $team, 'area' => 'user', 'metadata' => $metadata]);
    }

    protected function mayManageTarget(Team $team, int $userId): bool
    {
        if (! $this->canManageMembers($team, $this->user()->id) || (int) $team->owner_user_id === $userId) {
            return false;
        }
        $target = $team->members->firstWhere('id', $userId);
        if (! $target) {
            return false;
        }
        if ($this->isTeamOwner($team, $this->user()->id)) {
            return true;
        }

        return $userId !== (int) $this->user()->id
            && array_diff(TeamWorkspaceAccess::permissionsForUser($target, $team), TeamWorkspaceAccess::permissionsForUser($this->user(), $team)) === []
            && array_diff(TeamWorkspaceAccess::managedAccountIds($target, $team) ?? [], TeamWorkspaceAccess::accessibleAccountIds($this->user(), $team)) === [];
    }

    protected function assertManageTarget(Team $team, int $userId): void
    {
        abort_unless($this->mayManageTarget($team, $userId), 403);
    }

    public function applyPermissionPreset(string $preset, ?int $memberId = null): void
    {
        $team = $this->currentTeamOrFail();
        if ($memberId) {
            $this->assertManageTarget($team, $memberId);
        } else {
            abort_unless($this->canManageInvites($team, $this->user()->id), 403);
        }
        $presets = [
            'viewer' => ['channel.view'],
            'editor' => ['channel.view', 'post.create', 'chat.participate'],
            'reviewer' => ['channel.view', 'post.approve', 'chat.participate'],
        ];
        abort_unless(isset($presets[$preset]), 422);
        $permissions = array_values(array_intersect($presets[$preset], array_keys($this->availableTeamPermissions($team)), TeamWorkspaceAccess::permissionsForUser($this->user(), $team)));
        if ($memberId) {
            $this->memberPermissions[$memberId] = $permissions;
        } else {
            $this->invite_permissions = $permissions;
        }
    }

    protected function conversationQuery(Team $team)
    {
        $query = TeamConversation::where('team_id', $team->id);
        if (! $this->canUseChat($team, $this->user()->id)) {
            return $query->whereRaw('1=0');
        }
        if (! $this->isTeamOwner($team, $this->user()->id)) {
            $query->whereHas('participants', fn ($q) => $q->where('users.id', $this->user()->id));
        }

        return $query;
    }

    public function resendInvite(int $id): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canManageInvites($team, $this->user()->id), 403);
        $invite = TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->findOrFail($id);
        abort_if($invite->expires_at?->isPast() || ! filled($invite->email), 422);
        $key = 'team-invite-delivery:'.$id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->notifyError(__('Wait a minute before retrying this invitation.'));

            return;
        }
        RateLimiter::hit($key, 60);
        $this->errorMessage = null;
        $this->deliverInvite($invite);
        if (! $this->errorMessage) {
            $this->notifySuccess(__('Invitation handed to the configured mail server.'));
        }
    }

    protected function deliverInvite(TeamInvitation $invite): void
    {
        if (! filled($invite->email)) {
            return;
        }
        $metadata = $invite->metadata ?? [];
        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport', $mailer);
        if (in_array($transport, ['log', 'array', 'failover'], true)) {
            $invite->update(['metadata' => array_merge($metadata, ['delivery_status' => 'not_configured'])]);
            $this->notifyError(__('Email delivery is not configured. The invitation is saved; contact your administrator to enable email.'));

            return;
        }
        try {
            Mail::to($invite->email)->send(new TeamInvitationMail($invite));
            $invite->update(['metadata' => array_merge($metadata, ['delivery_status' => 'sent', 'delivery_at' => now()->toIso8601String()])]);
        } catch (\Throwable $e) {
            report($e);
            $invite->update(['metadata' => array_merge($metadata, ['delivery_status' => 'failed'])]);
            $this->notifyError(__('Email could not be sent. The invitation is saved and can be retried.'));
        }
    }

    protected function decideReview(int $reviewId, bool $approve): void
    {
        $team = $this->currentTeamOrFail();
        abort_unless($this->canApprovePosts($team, $this->user()->id), 403);
        $this->validate(['reviewNotes.'.$reviewId => ['nullable', 'string', 'max:2000']]);
        $changed = DB::transaction(function () use ($team, $reviewId, $approve) {
            $review = TeamPostReview::where('team_id', $team->id)->whereKey($reviewId)->lockForUpdate()->firstOrFail();
            if ($review->status !== 'pending') {
                return false;
            }
            $post = PublishingPost::where('team_id', $team->id)->whereKey($review->post_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int) $post->account_id, TeamWorkspaceAccess::accessibleAccountIds($this->user(), $team), true), 403);
            abort_unless((int) $post->status === PublishingPost::STATUS_DRAFT, 422, __('This post has changed. Reload before deciding.'));
            $post->update(['status' => $approve ? PublishingPost::STATUS_SCHEDULED : PublishingPost::STATUS_DRAFT, 'changed' => now()->timestamp]);
            $review->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by_user_id' => $this->user()->id, 'decision_note' => trim((string) ($this->reviewNotes[$reviewId] ?? '')) ?: null, 'decided_at' => now()]);
            $this->recordActivity($team, $approve ? 'post.approved' : 'post.rejected', $approve ? __('Post approved.') : __('Post rejected.'), ['review_id' => $reviewId, 'post_id' => $post->id]);
            if ($approve && ($review->metadata['schedule_mode'] ?? '') === 'immediately') {
                DB::afterCommit(fn () => PublishScheduledPostJob::dispatch((int) $post->id));
            }

            return true;
        }, 3);
        unset($this->reviewNotes[$reviewId]);
        $this->notifySuccess($changed ? __('Decision saved.') : __('This review has already been decided.'));
    }
}
