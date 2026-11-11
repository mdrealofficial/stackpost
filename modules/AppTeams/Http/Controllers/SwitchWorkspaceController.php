<?php

namespace Modules\AppTeams\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;

class SwitchWorkspaceController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $teamId = (int) $request->integer('team');
        $team = $this->accessibleTeams((int) $user->id)->firstWhere('id', $teamId);
        abort_unless($team, 403);

        session(['portal_team_id' => $team->id]);

        $redirect = (string) $request->query('redirect', '');
        $appBaseUrl = rtrim(url('/'), '/');

        if ($redirect !== '' && str_starts_with($redirect, $appBaseUrl)) {
            return redirect()->to($this->normalizeRedirectUrl($redirect, $team->id, $appBaseUrl));
        }

        return redirect()->route('portal.teams', ['team' => $team->id]);
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

    protected function normalizeRedirectUrl(string $redirect, int $teamId, string $appBaseUrl): string
    {
        if (! str_starts_with($redirect, $appBaseUrl)) {
            return $redirect;
        }

        $relativePath = '/'.ltrim(Str::after($redirect, $appBaseUrl), '/');
        $parts = parse_url($relativePath);
        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');

        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($path === '/portal/teams' || $path === '/v12/stackposts/portal/teams') {
            $query['team'] = $teamId;
            unset($query['conversation']);
        }

        $normalized = $appBaseUrl.$path;
        $queryString = http_build_query($query);

        if ($queryString !== '') {
            $normalized .= '?'.$queryString;
        }

        if (! empty($parts['fragment'])) {
            $normalized .= '#'.$parts['fragment'];
        }

        return $normalized;
    }
}
