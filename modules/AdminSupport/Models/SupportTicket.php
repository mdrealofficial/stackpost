<?php

namespace Modules\AdminSupport\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'pin' => 'boolean',
            'user_read' => 'boolean',
            'admin_read' => 'boolean',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id_secure';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'open_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'cate_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupportType::class, 'type_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(SupportLabel::class, 'support_map_labels', 'ticket_id', 'label_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupportComment::class, 'ticket_id')->orderBy('created');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('title', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%")
                ->orWhere('id_secure', 'like', "%{$term}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                    $userQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }

    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            2 => __('Resolved'),
            0 => __('Closed'),
            default => __('Open'),
        };
    }

    public function statusVariant(): string
    {
        return match ((int) $this->status) {
            2 => 'success',
            0 => 'neutral',
            default => 'primary',
        };
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->created) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->created)->format($format);
    }

    public function changedAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->changed) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->changed)->format($format);
    }

    public function changedFromHuman(): ?string
    {
        if (! $this->changed) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->changed)->diffForHumans();
    }

    public function excerpt(int $limit = 180): string
    {
        $content = trim(strip_tags((string) $this->content));

        if ($content === '') {
            return __('No details');
        }

        return mb_strimwidth($content, 0, $limit, '...');
    }
}
