<?php

namespace Modules\AdminSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportLabel extends Model
{
    protected $table = 'support_labels';

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
