<?php

namespace Modules\AdminSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportType extends Model
{
    protected $table = 'support_types';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }
}
