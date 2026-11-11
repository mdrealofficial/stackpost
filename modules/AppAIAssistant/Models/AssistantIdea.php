<?php

namespace Modules\AppAIAssistant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssistantIdea extends Model
{
    protected $table = 'ai_assistant_ideas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'variants' => 'array',
            'selected_account_ids' => 'array',
            'publishing_post_ids' => 'array',
            'metadata' => 'array',
            'generated_at' => 'datetime',
            'approved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $ownerUserId, ?int $teamId): Builder
    {
        return $query
            ->where('owner_user_id', $ownerUserId)
            ->when(
                $teamId,
                fn (Builder $builder, int $id): Builder => $builder->where('team_id', $id),
                fn (Builder $builder): Builder => $builder->whereNull('team_id')
            );
    }

    public function captionForProvider(string $providerKey): string
    {
        $variants = collect((array) $this->variants)->filter(fn ($variant) => is_array($variant));
        $platform = static::platformForProvider($providerKey);
        $variant = $variants->first(function (array $item) use ($platform): bool {
            $candidate = strtolower(trim((string) ($item['platform'] ?? '')));

            return $candidate === $platform
                || str_starts_with($candidate, $platform.'_')
                || str_starts_with($platform, $candidate.'_');
        });

        if (! is_array($variant)) {
            $variant = $variants->first();
        }

        if (! is_array($variant)) {
            return trim((string) $this->brief);
        }

        $hashtags = collect((array) ($variant['hashtags'] ?? []))
            ->map(fn ($tag) => ltrim(trim((string) $tag), '#'))
            ->filter()
            ->map(fn ($tag) => '#'.preg_replace('/\s+/', '', $tag))
            ->implode(' ');

        return trim(implode("\n\n", array_filter([
            trim((string) ($variant['hook'] ?? '')),
            trim((string) ($variant['caption'] ?? '')),
            trim((string) ($variant['cta'] ?? '')),
            $hashtags,
        ])));
    }

    public static function platformForProvider(string $providerKey): string
    {
        $key = strtolower(trim($providerKey));

        return match (true) {
            str_contains($key, 'linkedin') => 'linkedin',
            str_contains($key, 'instagram') => 'instagram',
            str_contains($key, 'facebook') => 'facebook',
            str_contains($key, 'threads') => 'threads',
            str_contains($key, 'tiktok') => 'tiktok',
            str_contains($key, 'youtube') => 'youtube',
            str_contains($key, 'pinterest') => 'pinterest',
            $key === 'twitter' || str_starts_with($key, 'twitter_') => 'x',
            default => $key,
        };
    }
}
