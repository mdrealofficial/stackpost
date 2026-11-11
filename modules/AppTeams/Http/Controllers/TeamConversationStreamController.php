<?php

namespace Modules\AppTeams\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppTeams\Models\TeamConversation;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamConversationStreamController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $team = $this->resolveTeam($request, (int) $user->id);
        abort_if(! $team, 404);
        abort_unless($this->canUseChat($team, (int) $user->id), 403);

        $conversationId = (int) $request->integer('conversation');
        $activeConversation = $this->roomQuery($team)
            ->when(
                $conversationId > 0,
                fn ($query) => $query->whereKey($conversationId),
                fn ($query) => $query->latest('last_message_at')
            )
            ->first();

        abort_if($activeConversation && ! $this->canAccessConversation($activeConversation, (int) $user->id), 403);

        // Each reconnect rechecks current membership and room access. Do not hold a PHP worker for a minute.
        return response()->stream(function () use ($team, $conversationId): void {
            echo "retry: 15000\n";
            echo "event: snapshot\n";
            echo 'data: '.json_encode($this->buildPayload($team, $conversationId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store, must-revalidate', 'X-Accel-Buffering' => 'no']);
    }

    protected function buildPayload(Team $team, int $conversationId): array
    {
        $activeConversation = $this->roomQuery($team)
            ->when(
                $conversationId > 0,
                fn ($query) => $query->whereKey($conversationId),
                fn ($query) => $query->latest('last_message_at')
            )
            ->first();

        if ($activeConversation) {
            $activeConversation->load([
                'participants' => fn ($query) => $query->orderBy('name'),
                'messages' => fn ($query) => $query->with('author')->latest()->limit(40),
            ]);

            $activeConversation->setRelation(
                'messages',
                $activeConversation->messages->sortBy('created_at')->values()
            );
        }

        $conversations = $this->roomQuery($team)
            ->latest('last_message_at')
            ->withCount('messages')
            ->get();

        return [
            'conversations' => $conversations->map(fn (TeamConversation $conversation) => [
                'id' => (int) $conversation->id,
                'title' => $conversation->title ?: __('Untitled room'),
                'messages_count' => (int) $conversation->messages_count,
                'messages_label' => number_format((int) $conversation->messages_count).' '.__('messages'),
                'last_message_label' => $conversation->last_message_at?->diffForHumans() ?: __('new'),
                'href' => route('portal.teams', ['team' => $team->id, 'conversation' => $conversation->id]),
                'active' => (int) ($activeConversation?->id ?? 0) === (int) $conversation->id,
            ])->values()->all(),
            'activeConversation' => $activeConversation ? [
                'id' => (int) $activeConversation->id,
                'title' => $activeConversation->title ?: __('Untitled room'),
                'description' => $activeConversation->description,
                'participants_label' => $activeConversation->participants->pluck('name')->join(', '),
                'messages' => $activeConversation->messages->map(fn ($message) => [
                    'id' => (int) $message->id,
                    'author_name' => $message->author?->name ?: __('Unknown'),
                    'body' => (string) $message->body,
                    'sent_label' => $message->sent_at?->diffForHumans() ?: $message->created_at?->diffForHumans(),
                ])->values()->all(),
            ] : null,
        ];
    }

    protected function roomQuery(Team $team)
    {
        $userId = (int) auth()->id();
        $query = TeamConversation::where('team_id', $team->id);
        if (! $this->canUseChat($team, $userId)) {
            return $query->whereRaw('1=0');
        }
        if ((int) $team->owner_user_id !== $userId) {
            $query->whereHas('participants', fn ($q) => $q->where('users.id', $userId));
        }

        return $query;
    }

    protected function resolveTeam(Request $request, int $userId): ?Team
    {
        $teamId = (int) $request->integer('team', (int) session('portal_team_id', 0));
        $teams = $this->accessibleTeams($userId);

        if ($teams->isEmpty()) {
            return null;
        }

        if ($teamId > 0) {
            return $teams->firstWhere('id', $teamId);
        }

        return $teams->first();
    }

    protected function accessibleTeams(int $userId): Collection
    {
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

    protected function canUseChat(Team $team, int $userId): bool
    {
        $user = User::query()->find($userId);

        return TeamWorkspaceAccess::hasPermission($user, 'chat.manage', $team)
            || TeamWorkspaceAccess::hasPermission($user, 'chat.participate', $team);
    }

    protected function canAccessConversation(TeamConversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('users.id', $userId)->exists()
            || $conversation->team()->where('owner_user_id', $userId)->exists();
    }
}
