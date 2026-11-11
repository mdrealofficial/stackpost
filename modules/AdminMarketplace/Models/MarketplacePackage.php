<?php

namespace Modules\AdminMarketplace\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplacePackage extends Model
{
    protected $table = 'marketplace_packages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'providers' => 'array',
            'meta' => 'array',
            'is_active' => 'boolean',
            'product_id' => 'integer',
            'installed_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
