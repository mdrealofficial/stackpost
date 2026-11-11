<?php

namespace Modules\AdminUser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_user_id',
        'enabled_modules',
    ];

    protected function casts(): array
    {
        return [
            'enabled_modules' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'permissions', 'managed_account_ids'])
            ->withTimestamps();
    }
}
