<?php

namespace Modules\AdminLanguages\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageTranslation extends Model
{
    protected $fillable = [
        'language_code',
        'key',
        'value',
        'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'bool',
        ];
    }
}
