<?php

namespace Modules\AdminLanguages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'name',
        'native_name',
        'code',
        'icon',
        'direction',
        'is_default',
        'is_active',
        'auto_translate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'bool',
            'is_active' => 'bool',
            'auto_translate' => 'bool',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
