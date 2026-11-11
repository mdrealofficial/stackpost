<?php

namespace Modules\AppRssSchedules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class RssSchedule extends Model
{
    protected $table = 'rss_schedules';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_ids' => 'array',
            'settings' => 'array',
            'time_posts' => 'array',
            'weekdays' => 'array',
            'status' => 'boolean',
            'start_at' => 'integer',
            'end_at' => 'integer',
            'last_checked_at' => 'integer',
            'last_queued_at' => 'integer',
            'next_run_at' => 'integer',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            if (blank($schedule->id_secure)) {
                $schedule->id_secure = Str::random(32);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id_secure';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RssScheduleHistory::class, 'schedule_id');
    }

    public function shouldRunAt(int $timestamp): bool
    {
        if ($this->isDemo()) {
            return false;
        }
        if (! $this->status) {
            return false;
        }

        if ($this->start_at && $timestamp < (int) $this->start_at) {
            return false;
        }

        if ($this->end_at && $timestamp > (int) $this->end_at) {
            return false;
        }

        if (! $this->next_run_at) {
            return true;
        }

        return (int) $this->next_run_at <= $timestamp;
    }

    public function isDemo(): bool
    {
        return data_get($this->settings, 'source') === 'admin-faker';
    }

    public static function normalizeWeekdays(array $values): array
    {
        $days = array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], false);
        foreach ($values as $key => $enabled) {
            $day = ucfirst(strtolower(substr(is_int($key) ? (string) $enabled : (string) $key, 0, 3)));
            if (array_key_exists($day, $days)) {
                $days[$day] = is_int($key) ? true : (bool) $enabled;
            }
        }

        return $days;
    }

    public function getWeekdaysAttribute($value): array
    {
        return self::normalizeWeekdays(is_array($value) ? $value : (json_decode($value ?? '[]', true) ?: []));
    }
}
