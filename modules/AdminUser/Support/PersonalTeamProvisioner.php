<?php

namespace Modules\AdminUser\Support;

use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class PersonalTeamProvisioner
{
    public function ensureForUser(User $user): Team
    {
        $team = Team::query()->firstWhere('owner_user_id', $user->id);

        if (! $team) {
            $team = Team::query()->create([
                'name' => $user->name."'s Team",
                'slug' => $this->uniqueTeamSlug($user->username.'-team'),
                'description' => 'Default team for '.$user->name,
                'owner_user_id' => $user->id,
            ]);
        }

        $team->members()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'permissions' => json_encode([
                    'team.manage',
                    'member.manage',
                    'post.approve',
                    'post.publish',
                    'chat.manage',
                ], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        return $team;
    }

    protected function uniqueTeamSlug(string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'team';
        $counter = 2;

        while (Team::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
