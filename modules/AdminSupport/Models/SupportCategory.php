<?php

namespace Modules\AdminSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCategory extends Model
{
    protected $table = 'support_categories';

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
