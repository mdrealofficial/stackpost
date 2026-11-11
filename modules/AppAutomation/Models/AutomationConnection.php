<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AutomationConnection extends Model
{
    protected $table = 'automation_connections';

    protected $guarded = [];

    protected $hidden = ['encrypted_credentials'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function driveChannel(): HasOne
    {
        return $this->hasOne(AutomationDriveChannel::class, 'connection_id');
    }

    public function folderWatches(): HasMany
    {
        return $this->hasMany(AutomationDriveFolderWatch::class, 'connection_id');
    }

    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (): array => filled($this->encrypted_credentials) ? (array) decrypt($this->encrypted_credentials) : [],
            set: fn (mixed $value): array => ['encrypted_credentials' => filled($value) ? encrypt((array) $value) : null],
        );
    }
}
