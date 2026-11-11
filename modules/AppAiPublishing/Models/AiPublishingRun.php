<?php

namespace Modules\AppAiPublishing\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AiPublishingRun extends Model
{
    protected $table = 'ai_publishing_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_ids' => 'array',
            'label_ids' => 'array',
            'prompt_ids' => 'array',
            'schedule_config' => 'array',
            'generation_config' => 'array',
            'stats' => 'array',
            'last_processed_at' => 'datetime',
        ];
    }

    public function isDemo(): bool
    {
        return data_get($this->generation_config, 'source') === 'admin-faker';
    }

    public function hasEnded(): bool
    {
        $end = data_get($this->schedule_config, 'end_at');

        return filled($end) && now($this->timezone())->gt(Carbon::parse($end, $this->timezone())->endOfDay());
    }

    public function timezone(): string
    {
        return (string) data_get($this->schedule_config, 'timezone', config('app.timezone'));
    }

    public function occurrences()
    {
        return $this->hasMany(AiPublishingOccurrence::class, 'run_id');
    }

    public function displayStatus(): string
    {
        if ($this->isDemo()) {
            return 'sample';
        }
        if ($this->hasEnded()) {
            return 'expired';
        }

        return match ($this->status) {
            'queued' => 'enabled', 'failed' => 'attention', default => $this->status
        };
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_user_id', $userId);
    }
}
