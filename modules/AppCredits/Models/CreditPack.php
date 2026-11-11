<?php

namespace Modules\AppCredits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CreditPack extends Model
{
    protected $table = 'credit_packs';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'credits',
        'price',
        'currency',
        'currency_symbol',
        'status',
        'featured',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price' => 'decimal:2',
            'status' => 'boolean',
            'featured' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $pack): void {
            if (! filled($pack->slug)) {
                $pack->slug = Str::slug((string) $pack->name);
            }

            $pack->currency = strtoupper((string) ($pack->currency ?: 'USD'));
            $pack->currency_symbol = (string) ($pack->currency_symbol ?: '$');
        });
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(CreditTopupLedger::class);
    }
}
